import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Month-navigable dashboard calendar. Receives a { "Y-m-d": [events] } map
// from the server and marks days that carry holidays or meetings.
Alpine.data('hrmsCalendar', (events, todayIso) => {
    const iso = (d) => d.getFullYear() + '-' +
        String(d.getMonth() + 1).padStart(2, '0') + '-' +
        String(d.getDate()).padStart(2, '0');

    const today = new Date(todayIso + 'T00:00:00');

    return {
        events: events || {},
        cursor: new Date(today.getFullYear(), today.getMonth(), 1),

        get monthLabel() {
            return this.cursor.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        },

        // Flat 7-column grid; leading/trailing nulls pad the first and last weeks.
        get days() {
            const year = this.cursor.getFullYear();
            const month = this.cursor.getMonth();
            const cells = [];

            for (let i = 0; i < new Date(year, month, 1).getDay(); i++) {
                cells.push(null);
            }

            const total = new Date(year, month + 1, 0).getDate();

            for (let day = 1; day <= total; day++) {
                const date = new Date(year, month, day);
                const key = iso(date);
                const evts = this.events[key] || [];
                const isToday = key === todayIso;
                const holiday = evts.some((e) => e.type === 'holiday');

                let classes = 'text-gray-700 hover:bg-gray-100';
                if (isToday) {
                    classes = 'font-semibold text-white';
                } else if (date.getDay() === 0) {
                    classes = 'text-red-500 hover:bg-gray-100';
                }

                let dotColor = '';
                if (evts.length) {
                    dotColor = isToday ? '#ffffff' : holiday ? '#ef4444' : '#2f80ed';
                }

                cells.push({
                    day,
                    classes,
                    dotColor,
                    style: isToday ? 'background-color:#2f80ed;' : '',
                    label: evts.map((e) => e.title).join(', '),
                });
            }

            while (cells.length % 7 !== 0) {
                cells.push(null);
            }

            return cells;
        },

        shift(delta) {
            this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + delta, 1);
        },

        goToday() {
            this.cursor = new Date(today.getFullYear(), today.getMonth(), 1);
        },
    };
});


// Live screen viewer. Polls the server for the newest frame the agent has
// pushed; it cannot pull anything from the employee's machine itself, so an
// agent that is offline or has not shown the indicator simply yields no
// frames and the page says so.
Alpine.data('liveView', (frameUrl) => ({
    frame: null,
    status: 'requested',
    notified: false,
    open: true,
    streaming: false,
    timer: null,

    start() {
        this.poll();
        // Roughly 2 fps: fast enough to follow what somebody is doing, slow
        // enough not to hammer the server.
        this.timer = setInterval(() => this.poll(), 500);

        // A forgotten background tab should not keep somebody's screen on
        // the wire.
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                clearInterval(this.timer);
                this.timer = null;
            } else if (!this.timer && this.open) {
                this.timer = setInterval(() => this.poll(), 500);
            }
        });
    },

    async poll() {
        try {
            const response = await fetch(frameUrl, { headers: { Accept: 'application/json' } });

            if (!response.ok) return;

            const data = await response.json();

            this.status = data.status;
            this.notified = data.notified;
            this.open = data.open;
            this.frame = data.frame;
            this.streaming = data.open && data.frame !== null;

            if (!data.open) {
                clearInterval(this.timer);
                this.timer = null;
                this.frame = null;
            }
        } catch (e) {
            // A dropped poll is not worth surfacing; the next one in half a
            // second will either work or not.
        }
    },

    statusLabel() {
        if (!this.open) return 'Session ended';
        if (this.streaming) return 'Live';
        if (this.notified) return 'Indicator shown, waiting for the first frame';

        return 'Waiting for the agent to show the indicator';
    },

    waitingMessage() {
        if (!this.open) return 'This session has ended.';
        if (this.notified) return 'The employee has been notified. Waiting for the first frame…';

        return 'Waiting for the agent to confirm the on-screen indicator. '
             + 'If their machine is asleep or offline, nothing will arrive.';
    },
}));


Alpine.start();
