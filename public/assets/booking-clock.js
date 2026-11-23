(function (root) {
  'use strict';
  class BookingClock {
    constructor(serverNow, monotonic = () => performance.now()) {
      this.monotonic = monotonic;
      if (!this.sync(serverNow)) throw new Error('Jam server tidak valid');
    }
    sync(serverNow) {
      const parsed = Date.parse(serverNow);
      if (!Number.isFinite(parsed)) return false;
      this.epoch = parsed;
      this.anchor = this.monotonic();
      return true;
    }
    now() { return this.epoch + Math.max(0, this.monotonic() - this.anchor); }
    today() {
      const parts = new Intl.DateTimeFormat('en-CA', {timeZone: 'Asia/Jakarta', year: 'numeric', month: '2-digit', day: '2-digit'}).formatToParts(new Date(this.now()));
      const values = Object.fromEntries(parts.map(part => [part.type, part.value]));
      return `${values.year}-${values.month}-${values.day}`;
    }
    slot(date, time) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !/^\d{2}:\d{2}$/.test(time)) return NaN;
      return Date.parse(`${date}T${time}:00+07:00`);
    }
    expired(date, time) {
      const start = this.slot(date, time);
      return Number.isFinite(start) && start <= this.now();
    }
    ended(date, time, hours) {
      const start = this.slot(date, time);
      return Number.isFinite(start) && Number(hours) > 0 && start + Number(hours) * 3600000 <= this.now();
    }
    static sameSchedule(a, b) {
      return ['court', 'date', 'time', 'duration'].every(key => String(a[key] ?? '') === String(b[key] ?? ''));
    }
  }
  root.SsrBookingClock = BookingClock;
  if (typeof module !== 'undefined' && module.exports) module.exports = BookingClock;
})(typeof window === 'undefined' ? globalThis : window);
