'use strict';

const test = require('node:test');
const assert = require('node:assert');

const { Capture } = require('../src/main/capture');

const BYTES_PER_PIXEL = 4;

/**
 * A stand-in for Electron's nativeImage.
 *
 * Holds a real BGRA buffer so the stitching maths can be checked pixel by
 * pixel rather than just asserting that something came back.
 */
function fakeImage(width, height, fill) {
  const bitmap = Buffer.alloc(width * height * BYTES_PER_PIXEL);

  for (let i = 0; i < bitmap.length; i += BYTES_PER_PIXEL) {
    bitmap[i] = fill.b;
    bitmap[i + 1] = fill.g;
    bitmap[i + 2] = fill.r;
    bitmap[i + 3] = 255;
  }

  return {
    getSize: () => ({ width, height }),
    toBitmap: () => bitmap,
    isEmpty: () => width === 0 || height === 0,
    resize: ({ width: w, height: h }) => fakeImage(w, h, fill),
    toJPEG: () => Buffer.from(`jpeg:${width}x${height}`),
  };
}

function fakeNativeImage() {
  return {
    createFromBitmap: (buffer, { width, height }) => ({
      getSize: () => ({ width, height }),
      toBitmap: () => buffer,
      isEmpty: () => false,
      resize: ({ width: w, height: h }) => ({
        getSize: () => ({ width: w, height: h }),
        toBitmap: () => buffer,
        isEmpty: () => false,
        toJPEG: () => Buffer.from(`jpeg:${w}x${h}`),
      }),
      toJPEG: () => Buffer.from(`jpeg:${width}x${height}`),
    }),
  };
}

/** Read one pixel out of a BGRA canvas. */
function pixelAt(buffer, canvasWidth, x, y) {
  const i = (y * canvasWidth + x) * BYTES_PER_PIXEL;

  return { b: buffer[i], g: buffer[i + 1], r: buffer[i + 2], a: buffer[i + 3] };
}

function build({ displays, sources }) {
  const captured = {};

  const capture = new Capture({
    screen: {
      getAllDisplays: () => displays,
      getPrimaryDisplay: () => displays[0],
    },
    desktopCapturer: {
      getSources: async (opts) => {
        captured.opts = opts;
        return sources;
      },
    },
    nativeImage: fakeNativeImage(),
    logger: { warn() {}, info() {} },
  });

  return { capture, captured };
}

const RED = { r: 255, g: 0, b: 0 };
const BLUE = { r: 0, g: 0, b: 255 };

const SETTINGS = { screenshot_max_dimension: 10000, screenshot_jpeg_quality: 60 };

test('a single display is captured without stitching', async () => {
  const { capture } = build({
    displays: [{ id: 1, size: { width: 100, height: 80 }, scaleFactor: 1, bounds: { x: 0 } }],
    sources: [{ display_id: '1', thumbnail: fakeImage(100, 80, RED) }],
  });

  const shot = await capture.captureAll(SETTINGS);

  assert.equal(shot.displays, 1);
  assert.equal(shot.width, 100);
  assert.equal(shot.height, 80);
});

test('two displays are stitched side by side', async () => {
  const { capture } = build({
    displays: [
      { id: 1, size: { width: 100, height: 80 }, scaleFactor: 1, bounds: { x: 0 } },
      { id: 2, size: { width: 60, height: 80 }, scaleFactor: 1, bounds: { x: 100 } },
    ],
    sources: [
      { display_id: '1', thumbnail: fakeImage(100, 80, RED) },
      { display_id: '2', thumbnail: fakeImage(60, 80, BLUE) },
    ],
  });

  const shot = await capture.captureAll(SETTINGS);

  assert.equal(shot.displays, 2);
  assert.equal(shot.width, 160, 'widths add up');
  assert.equal(shot.height, 80);
});

test('displays are ordered by their real desktop position, not by id', async () => {
  // Display 2 is physically to the LEFT (bounds.x = 0) but has the higher
  // id, and it is listed second. The stitched image must still put it on
  // the left, so assert on the actual pixels rather than just the width.
  const displays = [
    { id: 1, size: { width: 40, height: 20 }, scaleFactor: 1, bounds: { x: 40 } },
    { id: 2, size: { width: 40, height: 20 }, scaleFactor: 1, bounds: { x: 0 } },
  ];

  let canvas = null;
  let canvasWidth = 0;

  const capture = new Capture({
    screen: { getAllDisplays: () => displays, getPrimaryDisplay: () => displays[0] },
    desktopCapturer: {
      getSources: async () => [
        { display_id: '1', thumbnail: fakeImage(40, 20, RED) },
        { display_id: '2', thumbnail: fakeImage(40, 20, BLUE) },
      ],
    },
    nativeImage: {
      createFromBitmap: (buffer, { width, height }) => {
        canvas = buffer;
        canvasWidth = width;

        return {
          getSize: () => ({ width, height }),
          toBitmap: () => buffer,
          isEmpty: () => false,
          resize: () => ({ getSize: () => ({ width, height }), toJPEG: () => Buffer.from('jpeg') }),
          toJPEG: () => Buffer.from('jpeg'),
        };
      },
    },
    logger: { warn() {}, info() {} },
  });

  const shot = await capture.captureAll(SETTINGS);

  assert.equal(shot.width, 80);
  assert.ok(canvas, 'the stitcher composited a canvas');

  // Left half must be BLUE (display 2, bounds.x = 0).
  assert.deepEqual(pixelAt(canvas, canvasWidth, 5, 10), { b: 255, g: 0, r: 0, a: 255 }, 'left half is the display at x=0');

  // Right half must be RED (display 1, bounds.x = 40).
  assert.deepEqual(pixelAt(canvas, canvasWidth, 45, 10), { b: 0, g: 0, r: 255, a: 255 }, 'right half is the display at x=40');
});

test('a shorter display is centred vertically and the gap is opaque black', async () => {
  const displays = [
    { id: 1, size: { width: 4, height: 8 }, scaleFactor: 1, bounds: { x: 0 } },
    { id: 2, size: { width: 4, height: 4 }, scaleFactor: 1, bounds: { x: 4 } },
  ];

  const capture = new Capture({
    screen: { getAllDisplays: () => displays, getPrimaryDisplay: () => displays[0] },
    desktopCapturer: {
      getSources: async () => [
        { display_id: '1', thumbnail: fakeImage(4, 8, RED) },
        { display_id: '2', thumbnail: fakeImage(4, 4, BLUE) },
      ],
    },
    nativeImage: {
      createFromBitmap: (buffer, { width, height }) => {
        // Inspect the composited canvas directly.
        assert.equal(width, 8);
        assert.equal(height, 8);

        // Tall display fills its full column.
        assert.deepEqual(pixelAt(buffer, width, 0, 0), { b: 0, g: 0, r: 255, a: 255 });
        assert.deepEqual(pixelAt(buffer, width, 0, 7), { b: 0, g: 0, r: 255, a: 255 });

        // Short display sits centred: rows 2..5, so row 0 above it is black
        // and row 2 is its first pixel.
        assert.deepEqual(pixelAt(buffer, width, 4, 0), { b: 0, g: 0, r: 0, a: 255 }, 'gap is opaque black');
        assert.deepEqual(pixelAt(buffer, width, 4, 2), { b: 255, g: 0, r: 0, a: 255 }, 'centred at row 2');
        assert.deepEqual(pixelAt(buffer, width, 4, 5), { b: 255, g: 0, r: 0, a: 255 }, 'through row 5');
        assert.deepEqual(pixelAt(buffer, width, 4, 7), { b: 0, g: 0, r: 0, a: 255 }, 'black again below');

        return {
          getSize: () => ({ width, height }),
          toJPEG: () => Buffer.from('jpeg'),
          toBitmap: () => buffer,
          isEmpty: () => false,
          resize: () => ({ getSize: () => ({ width, height }), toJPEG: () => Buffer.from('jpeg') }),
        };
      },
    },
    logger: { warn() {}, info() {} },
  });

  const shot = await capture.captureAll({ screenshot_max_dimension: 10000, screenshot_jpeg_quality: 60 });

  assert.equal(shot.displays, 2);
});

test('a display with no matching source is skipped rather than failing the capture', async () => {
  const { capture } = build({
    displays: [
      { id: 1, size: { width: 50, height: 50 }, scaleFactor: 1, bounds: { x: 0 } },
      { id: 2, size: { width: 50, height: 50 }, scaleFactor: 1, bounds: { x: 50 } },
    ],
    sources: [{ display_id: '1', thumbnail: fakeImage(50, 50, RED) }],
  });

  const shot = await capture.captureAll(SETTINGS);

  assert.equal(shot.displays, 1, 'falls back to the one display that did report');
  assert.equal(shot.width, 50);
});

test('no sources at all returns null rather than throwing', async () => {
  const { capture } = build({
    displays: [
      { id: 1, size: { width: 50, height: 50 }, scaleFactor: 1, bounds: { x: 0 } },
      { id: 2, size: { width: 50, height: 50 }, scaleFactor: 1, bounds: { x: 50 } },
    ],
    sources: [],
  });

  assert.equal(await capture.captureAll(SETTINGS), null);
});

test('the stitched frame is downscaled to the configured maximum', async () => {
  const { capture } = build({
    displays: [
      { id: 1, size: { width: 1920, height: 1080 }, scaleFactor: 1, bounds: { x: 0 } },
      { id: 2, size: { width: 1920, height: 1080 }, scaleFactor: 1, bounds: { x: 1920 } },
    ],
    sources: [
      { display_id: '1', thumbnail: fakeImage(1920, 1080, RED) },
      { display_id: '2', thumbnail: fakeImage(1920, 1080, BLUE) },
    ],
  });

  // 3840 wide stitched, capped at 1600.
  const shot = await capture.captureAll({ screenshot_max_dimension: 1600, screenshot_jpeg_quality: 60 });

  assert.equal(shot.width, 1600);
  assert.equal(shot.height, 450, 'aspect ratio preserved');
});

test('the thumbnail request is sized for the largest display', async () => {
  const { capture, captured } = build({
    displays: [
      { id: 1, size: { width: 1280, height: 720 }, scaleFactor: 1, bounds: { x: 0 } },
      { id: 2, size: { width: 1920, height: 1200 }, scaleFactor: 2, bounds: { x: 1280 } },
    ],
    sources: [
      { display_id: '1', thumbnail: fakeImage(1280, 720, RED) },
      { display_id: '2', thumbnail: fakeImage(3840, 2400, BLUE) },
    ],
  });

  await capture.captureAll(SETTINGS);

  assert.equal(captured.opts.thumbnailSize.width, 3840, 'accounts for the 2x scale factor');
  assert.equal(captured.opts.thumbnailSize.height, 2400);
});

test('live frames cover every display too', async () => {
  const { capture } = build({
    displays: [
      { id: 1, size: { width: 100, height: 100 }, scaleFactor: 1, bounds: { x: 0 } },
      { id: 2, size: { width: 100, height: 100 }, scaleFactor: 1, bounds: { x: 100 } },
    ],
    sources: [
      { display_id: '1', thumbnail: fakeImage(100, 100, RED) },
      { display_id: '2', thumbnail: fakeImage(100, 100, BLUE) },
    ],
  });

  const frame = await capture.captureLiveFrame();

  assert.equal(frame.displays, 2, 'a live viewer sees both monitors, not just the primary');
});
