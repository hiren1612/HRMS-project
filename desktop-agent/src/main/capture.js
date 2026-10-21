'use strict';

const { execFile } = require('node:child_process');

/**
 * Screen capture, compression, and the privacy checks that gate it.
 *
 * Uses Electron's own desktopCapturer and nativeImage, so there is no
 * native module to compile and nothing for electron-builder to rebuild per
 * architecture. That matters: native screen-capture modules are the usual
 * cause of an installer that works on the build machine and nowhere else.
 */
class Capture {
  /**
   * @param {object} deps
   * @param {object} deps.desktopCapturer  Electron desktopCapturer
   * @param {object} deps.screen           Electron screen
   * @param {object} deps.nativeImage      Electron nativeImage
   * @param {object} deps.logger
   */
  constructor({ desktopCapturer, screen, nativeImage, logger = console }) {
    this.desktopCapturer = desktopCapturer;
    this.screen = screen;
    this.nativeImage = nativeImage;
    this.logger = logger;
  }

  /**
   * Capture every display and return one compressed JPEG.
   *
   * The screenshots table is unique on (device_id, captured_at), so one
   * moment is one row and one image. With more than one monitor that means
   * stitching them side by side rather than uploading a row per display:
   * a reviewer wants to see what the whole desk looked like at 10:05, not
   * two unrelated rows they have to pair up by timestamp.
   *
   * Displays are laid out left to right by their real desktop position, so
   * the stitched frame matches how the monitors actually sit.
   *
   * @param {object} settings  the server's resolved settings
   * @returns {Promise<{buffer: Buffer, width: number, height: number, displays: number}|null>}
   */
  async captureAll(settings) {
    const maxDimension = settings.screenshot_max_dimension || 1600;
    const quality = settings.screenshot_jpeg_quality || 60;

    const displays = this.screen.getAllDisplays();

    if (displays.length <= 1) {
      const single = await this.capturePrimary(settings);

      return single ? { ...single, displays: 1 } : null;
    }

    // One thumbnailSize covers every source, so ask for the largest display
    // and let the smaller ones come back at their own aspect ratio.
    const widest = Math.max(...displays.map((d) => Math.round(d.size.width * (d.scaleFactor || 1))));
    const tallest = Math.max(...displays.map((d) => Math.round(d.size.height * (d.scaleFactor || 1))));

    const sources = await this.desktopCapturer.getSources({
      types: ['screen'],
      thumbnailSize: { width: widest, height: tallest },
      fetchWindowIcons: false,
    });

    if (sources.length === 0) {
      this.logger.warn('capture: no screen source available');
      return null;
    }

    // Pair each display with its source and order by desktop x, so a monitor
    // physically on the left ends up on the left of the stitched image.
    const ordered = displays
      .map((display) => ({
        display,
        source: sources.find((s) => String(s.display_id) === String(display.id)),
      }))
      .filter((pair) => pair.source && !pair.source.thumbnail.isEmpty())
      .sort((a, b) => (a.display.bounds?.x || 0) - (b.display.bounds?.x || 0));

    if (ordered.length === 0) {
      this.logger.warn('capture: every display returned an empty frame');
      return null;
    }

    if (ordered.length === 1) {
      const only = this.#finish(ordered[0].source.thumbnail, maxDimension, quality);

      return { ...only, displays: 1 };
    }

    let stitched;

    try {
      stitched = this.#stitchHorizontal(ordered.map((pair) => pair.source.thumbnail));
    } catch (error) {
      // A stitch failure must not cost the whole capture: fall back to the
      // primary display rather than returning nothing for this interval.
      this.logger.warn('capture: could not stitch displays, falling back to primary', error.message);

      const single = await this.capturePrimary(settings);

      return single ? { ...single, displays: 1 } : null;
    }

    return { ...this.#finish(stitched, maxDimension, quality), displays: ordered.length };
  }

  /**
   * Lay images out left to right on one canvas.
   *
   * Works on the raw BGRA buffers nativeImage exposes, which keeps this to
   * plain JavaScript. A compositing library would be clearer to read but
   * would also be another binary inside an agent that antivirus software
   * already looks at closely.
   *
   * Shorter displays are centred vertically against the tallest, and the
   * gaps left over stay black rather than transparent, because JPEG has no
   * alpha channel to carry transparency anyway.
   *
   * @param {object[]} images  nativeImage instances
   */
  #stitchHorizontal(images) {
    const sizes = images.map((image) => image.getSize());
    const totalWidth = sizes.reduce((sum, size) => sum + size.width, 0);
    const maxHeight = Math.max(...sizes.map((size) => size.height));

    const BYTES_PER_PIXEL = 4;
    const canvas = Buffer.alloc(totalWidth * maxHeight * BYTES_PER_PIXEL);

    // Opaque black: BGRA, so only the alpha byte needs setting.
    for (let i = 3; i < canvas.length; i += BYTES_PER_PIXEL) canvas[i] = 255;

    let offsetX = 0;

    images.forEach((image, index) => {
      const { width, height } = sizes[index];
      const bitmap = image.toBitmap();
      const expected = width * height * BYTES_PER_PIXEL;

      if (bitmap.length < expected) {
        throw new Error(`display ${index}: bitmap is ${bitmap.length} bytes, expected ${expected}`);
      }

      const offsetY = Math.floor((maxHeight - height) / 2);

      for (let row = 0; row < height; row++) {
        const from = row * width * BYTES_PER_PIXEL;
        const to = ((offsetY + row) * totalWidth + offsetX) * BYTES_PER_PIXEL;

        bitmap.copy(canvas, to, from, from + width * BYTES_PER_PIXEL);
      }

      offsetX += width;
    });

    return this.nativeImage.createFromBitmap(canvas, { width: totalWidth, height: maxHeight });
  }

  /**
   * Downscale if needed and compress. Shared by the single and stitched paths.
   */
  #finish(image, maxDimension, quality) {
    const size = image.getSize();
    let out = image;

    if (Math.max(size.width, size.height) > maxDimension) {
      const ratio = maxDimension / Math.max(size.width, size.height);

      out = image.resize({
        width: Math.round(size.width * ratio),
        height: Math.round(size.height * ratio),
        quality: 'good',
      });
    }

    const finalSize = out.getSize();

    return { buffer: out.toJPEG(quality), width: finalSize.width, height: finalSize.height };
  }

  /**
   * Capture the primary display and return a compressed JPEG.
   *
   * @param {object} settings  the server's resolved settings
   * @returns {Promise<{buffer: Buffer, width: number, height: number}|null>}
   */
  async capturePrimary(settings) {
    const maxDimension = settings.screenshot_max_dimension || 1600;
    const quality = settings.screenshot_jpeg_quality || 60;

    const primary = this.screen.getPrimaryDisplay();
    const scale = primary.scaleFactor || 1;

    const sources = await this.desktopCapturer.getSources({
      types: ['screen'],
      thumbnailSize: {
        width: Math.round(primary.size.width * scale),
        height: Math.round(primary.size.height * scale),
      },
      fetchWindowIcons: false,
    });

    const source = this.#pickPrimary(sources, primary);

    if (!source) {
      this.logger.warn('capture: no screen source available');
      return null;
    }

    let image = source.thumbnail;

    if (image.isEmpty()) {
      this.logger.warn('capture: empty frame');
      return null;
    }

    const size = image.getSize();

    // Downscale before compressing. A screenshot exists to show what
    // somebody was working on; it does not need to be a pixel-accurate
    // copy of a 4K monitor, and the bandwidth is the employee's.
    if (Math.max(size.width, size.height) > maxDimension) {
      const ratio = maxDimension / Math.max(size.width, size.height);

      image = image.resize({
        width: Math.round(size.width * ratio),
        height: Math.round(size.height * ratio),
        quality: 'good',
      });
    }

    const finalSize = image.getSize();

    return {
      buffer: image.toJPEG(quality),
      width: finalSize.width,
      height: finalSize.height,
    };
  }

  /**
   * A smaller, cheaper frame for live viewing.
   *
   * Covers every display too. Watching one monitor of three and believing
   * it is the whole picture is worse than not watching at all.
   */
  async captureLiveFrame(maxDimension = 1280, quality = 45) {
    return this.captureAll({
      screenshot_max_dimension: maxDimension,
      screenshot_jpeg_quality: quality,
    });
  }

  /**
   * Is something on screen that must never be captured?
   *
   * Asks Windows for the foreground window's title and process name and
   * matches them against the server's exclusion list. Erring toward
   * skipping: if the check itself fails we treat the screen as excluded,
   * because a missed screenshot is a far smaller problem than a captured
   * password vault.
   *
   * @returns {Promise<boolean>}
   */
  async isExcluded(settings) {
    const patterns = (settings.excluded_windows || []).map((p) => String(p).toLowerCase()).filter(Boolean);

    if (patterns.length === 0) return false;

    let foreground;

    try {
      foreground = await this.foregroundWindow();
    } catch (error) {
      this.logger.warn('capture: could not read the foreground window, skipping capture', error.message);
      return true;
    }

    if (!foreground) return true;

    const haystack = `${foreground.title} ${foreground.process}`.toLowerCase();

    return patterns.some((pattern) => haystack.includes(pattern));
  }

  /**
   * Foreground window title and process name, via PowerShell.
   *
   * A native module (active-win and friends) would be faster, but it adds
   * a compiled dependency and, more importantly, the kind of binary that
   * antivirus software inspects closely. This runs once per capture
   * interval — every five minutes by default — so its cost is irrelevant.
   *
   * @returns {Promise<{title: string, process: string}|null>}
   */
  foregroundWindow() {
    const script = `
      Add-Type @"
using System;
using System.Runtime.InteropServices;
public class Fg {
  [DllImport("user32.dll")] public static extern IntPtr GetForegroundWindow();
  [DllImport("user32.dll")] public static extern int GetWindowTextLength(IntPtr h);
  [DllImport("user32.dll")] public static extern int GetWindowText(IntPtr h, System.Text.StringBuilder s, int n);
  [DllImport("user32.dll")] public static extern int GetWindowThreadProcessId(IntPtr h, out int pid);
}
"@
      $h = [Fg]::GetForegroundWindow()
      $len = [Fg]::GetWindowTextLength($h)
      $sb = New-Object System.Text.StringBuilder ($len + 1)
      [void][Fg]::GetWindowText($h, $sb, $sb.Capacity)
      $pid = 0
      [void][Fg]::GetWindowThreadProcessId($h, [ref]$pid)
      $proc = (Get-Process -Id $pid -ErrorAction SilentlyContinue).ProcessName
      [Console]::Out.Write((ConvertTo-Json @{ title = $sb.ToString(); process = "$proc" } -Compress))
    `;

    return new Promise((resolve, reject) => {
      execFile(
        'powershell.exe',
        ['-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-Command', script],
        { timeout: 5000, windowsHide: true },
        (error, stdout) => {
          if (error) return reject(error);

          try {
            const parsed = JSON.parse(String(stdout).trim());

            resolve({ title: parsed.title || '', process: parsed.process || '' });
          } catch (parseError) {
            reject(parseError);
          }
        },
      );
    });
  }

  #pickPrimary(sources, primaryDisplay) {
    if (sources.length === 0) return null;

    const byId = sources.find((s) => String(s.display_id) === String(primaryDisplay.id));

    return byId || sources[0];
  }
}

module.exports = { Capture };
