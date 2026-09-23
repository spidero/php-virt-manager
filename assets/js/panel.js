// php-virt-manager: live machine states and charts, polling stats.php every 5 s
(function () {
  'use strict';

  var INTERVAL = 5000;
  var POINTS = 60; // 5 minutes of samples
  var body = document.body;
  var node = body.getAttribute('data-node') || '';
  var initialState = body.getAttribute('data-state');
  var prev = null;
  var series = {};

  function css(name, fallback) {
    var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
  }

  // menu dots and dashboard tiles
  function updateStates(domains) {
    var counts = {};
    domains.forEach(function (d) {
      counts[d.state] = (counts[d.state] || 0) + 1;
      var dot = document.querySelector('[data-domain-dot="' + CSS.escape(d.name) + '"]');
      if (dot) {
        dot.className = 'text-' + d.color;
        dot.title = d.label;
      }
    });
    document.querySelectorAll('[data-count-state]').forEach(function (el) {
      el.textContent = counts[el.getAttribute('data-count-state')] || 0;
    });
  }

  function push(key, value) {
    var s = series[key] || (series[key] = []);
    s.push(value);
    if (s.length > POINTS) s.shift();
  }

  function format(value, unit) {
    if (unit === '%') return value.toFixed(1) + ' %';
    if (unit === 'MB') return Math.round(value) + ' MB';
    return (value < 10 ? value.toFixed(2) : value.toFixed(1)) + ' MB/s';
  }

  // line chart of one or more series on a canvas
  function draw(canvas) {
    var keys = canvas.getAttribute('data-series').split(',');
    var labels = canvas.getAttribute('data-labels').split(',');
    var unit = canvas.getAttribute('data-unit');
    var colors = [css('--bs-primary', '#0d6efd'), css('--bs-danger', '#dc3545')];
    var ratio = window.devicePixelRatio || 1;
    var w = canvas.clientWidth, h = canvas.clientHeight;
    canvas.width = w * ratio;
    canvas.height = h * ratio;
    var ctx = canvas.getContext('2d');
    ctx.scale(ratio, ratio);
    ctx.clearRect(0, 0, w, h);

    var max = unit === '%' ? 100 : 0;
    keys.forEach(function (k) { (series[k] || []).forEach(function (v) { max = Math.max(max, v); }); });
    if (max === 0) max = 1;
    max *= unit === '%' ? 1 : 1.15;

    var top = 18, bottom = h - 4, left = 4, right = w - 4;
    ctx.strokeStyle = css('--bs-border-color', '#dee2e6');
    ctx.lineWidth = 1;
    [0, 0.5, 1].forEach(function (f) {
      var y = Math.round(top + (bottom - top) * f) + 0.5;
      ctx.beginPath(); ctx.moveTo(left, y); ctx.lineTo(right, y); ctx.stroke();
    });

    ctx.font = '11px system-ui, sans-serif';
    var legendX = left;
    keys.forEach(function (k, i) {
      var s = series[k] || [];
      ctx.strokeStyle = colors[i % colors.length];
      ctx.lineWidth = 2;
      ctx.beginPath();
      s.forEach(function (v, j) {
        var x = right - (s.length - 1 - j) * (right - left) / (POINTS - 1);
        var y = bottom - (v / max) * (bottom - top);
        if (j === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
      });
      ctx.stroke();
      var text = labels[i] + ': ' + (s.length ? format(s[s.length - 1], unit) : '-');
      ctx.fillStyle = colors[i % colors.length];
      ctx.fillText(text, legendX, 12);
      legendX += ctx.measureText(text).width + 14;
    });
    ctx.fillStyle = css('--bs-secondary-color', '#6c757d');
    var maxText = format(max, unit);
    ctx.fillText(maxText, right - ctx.measureText(maxText).width, 12);
  }

  function updateCharts(sample, time) {
    if (prev) {
      var dt = time - prev.time;
      if (dt > 0) {
        var d = function (k) { return Math.max(0, sample[k] - prev.sample[k]) / dt; };
        push('cpu', Math.min(100, d('cpu_ns') / 1e9 / sample.vcpus * 100));
        push('mem', sample.mem_rss / 1024);
        push('disk_rd', d('disk_rd') / 1048576);
        push('disk_wr', d('disk_wr') / 1048576);
        push('net_rx', d('net_rx') / 1048576);
        push('net_tx', d('net_tx') / 1048576);
      }
    }
    prev = { sample: sample, time: time };
    document.querySelectorAll('canvas[data-series]').forEach(draw);
  }

  function poll() {
    if (document.hidden) return;
    var url = 'stats.php' + (node ? '?node=' + encodeURIComponent(node) : '');
    fetch(url, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data) return;
        updateStates(data.domains);
        if (node) {
          var current = data.domains.filter(function (d) { return d.name === node; })[0];
          // state changed elsewhere (or the machine is gone): reload for the right buttons
          if (!current || String(current.state) !== initialState) {
            window.location.reload();
            return;
          }
          if (data.node) updateCharts(data.node, data.time);
        }
      })
      .catch(function () {});
  }

  // progress bars of background jobs ([data-job]); the page reloads when one finishes
  function pollJobs() {
    var bars = document.querySelectorAll('[data-job]');
    if (!bars.length || document.hidden) return;
    var ids = Array.prototype.map.call(bars, function (el) { return el.getAttribute('data-job'); });
    fetch('jobs.php?ids=' + ids.join(','), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (states) {
        if (!states) return;
        var finished = false;
        bars.forEach(function (el) {
          var id = el.getAttribute('data-job');
          var job = states[id];
          if (!job || job.status === 'done' || job.status === 'failed') {
            finished = true;
            return;
          }
          var bar = el.firstElementChild;
          var known = job.progress !== null;
          bar.style.width = (known ? job.progress : 100) + '%';
          bar.textContent = known ? job.progress + '%' : '';
          bar.classList.toggle('bg-secondary', job.status === 'queued');
          var msg = document.querySelector('[data-job-message="' + id + '"]');
          if (msg) msg.textContent = job.message;
        });
        if (finished) window.location.reload();
      })
      .catch(function () {});
  }

  poll();
  setInterval(poll, INTERVAL);
  pollJobs();
  setInterval(pollJobs, 2000);
  window.addEventListener('resize', function () {
    document.querySelectorAll('canvas[data-series]').forEach(draw);
  });
})();
