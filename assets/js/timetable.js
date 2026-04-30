/**
 * PJJA timetable: category filters + venue-timezone "Happening now / Up next".
 */
(function () {
  "use strict";

  var WEEK = [
    "monday",
    "tuesday",
    "wednesday",
    "thursday",
    "friday",
    "saturday",
    "sunday",
  ];

  var WEEKDAY_TO_SLUG = {
    Monday: "monday",
    Tuesday: "tuesday",
    Wednesday: "wednesday",
    Thursday: "thursday",
    Friday: "friday",
    Saturday: "saturday",
    Sunday: "sunday",
  };

  function eachNode(nodeList, fn) {
    if (!nodeList || !fn) {
      return;
    }
    for (var i = 0; i < nodeList.length; i++) {
      fn(nodeList[i], i);
    }
  }

  function parseConfigScript(root) {
    var el = root.querySelector("script.clubworx-timetable-config[type='application/json']");
    if (!el || !el.textContent) {
      return {};
    }
    try {
      return JSON.parse(el.textContent);
    } catch (e) {
      return {};
    }
  }

  function getCfgValue(cfg, key, fallback) {
    if (cfg && Object.prototype.hasOwnProperty.call(cfg, key)) {
      return cfg[key];
    }
    return fallback;
  }

  function buildClassesByDayFromDom(root) {
    var byDay = {};
    eachNode(root.querySelectorAll(".clubworx-timetable-class"), function (row) {
      var day = row.getAttribute("data-day") || "";
      if (!day) {
        return;
      }
      if (!byDay[day]) {
        byDay[day] = [];
      }
      var start = parseInt(row.getAttribute("data-start-minutes") || "0", 10);
      if (isNaN(start)) {
        start = 0;
      }
      var nameEl = row.querySelector(".clubworx-timetable-class-name");
      var timeEl = row.querySelector(".clubworx-timetable-time");
      byDay[day].push({
        startMinutes: start,
        name: nameEl ? (nameEl.textContent || "").trim() : "",
        time: timeEl ? (timeEl.textContent || "").trim() : "",
        categoryClass: row.getAttribute("data-category") || "",
      });
    });

    for (var i = 0; i < WEEK.length; i++) {
      if (!byDay[WEEK[i]]) {
        byDay[WEEK[i]] = [];
      }
    }
    return byDay;
  }

  function getVenueClock(now, timeZone) {
    var wd = new Intl.DateTimeFormat("en-US", {
      timeZone: timeZone,
      weekday: "long",
    }).format(now);
    var parts = new Intl.DateTimeFormat("en-US", {
      timeZone: timeZone,
      hour: "numeric",
      minute: "numeric",
      hourCycle: "h23",
    }).formatToParts(now);
    var hour = 0;
    var minute = 0;
    for (var i = 0; i < parts.length; i++) {
      if (parts[i].type === "hour") {
        hour = parseInt(parts[i].value, 10);
      }
      if (parts[i].type === "minute") {
        minute = parseInt(parts[i].value, 10);
      }
    }
    var daySlug = WEEKDAY_TO_SLUG[wd];
    if (!daySlug) {
      daySlug = "monday";
    }
    return {
      daySlug: daySlug,
      minutesFromMidnight: hour * 60 + minute,
    };
  }

  function findNowAndNext(cfg, clock) {
    var dur = cfg.durationMinutes || 60;
    var byDay = cfg.classesByDay || {};
    var nowMin = clock.minutesFromMidnight;

    var startIdx = WEEK.indexOf(clock.daySlug);
    if (startIdx < 0) {
      startIdx = 0;
    }

    var k;
    var i;
    var day;
    var list;
    var c;
    var start;
    var end;

    for (k = 0; k < 7; k++) {
      day = WEEK[(startIdx + k) % 7];
      list = byDay[day] || [];
      for (i = 0; i < list.length; i++) {
        c = list[i];
        start = c.startMinutes;
        end = start + dur;
        if (k === 0 && start <= nowMin && nowMin < end) {
          return {
            mode: "now",
            day: day,
            cls: c,
          };
        }
      }
    }

    for (k = 0; k < 7; k++) {
      day = WEEK[(startIdx + k) % 7];
      list = byDay[day] || [];
      for (i = 0; i < list.length; i++) {
        c = list[i];
        start = c.startMinutes;
        end = start + dur;
        if (k === 0) {
          if (start <= nowMin && nowMin < end) {
            continue;
          }
          if (nowMin >= end) {
            continue;
          }
          if (start > nowMin) {
            return {
              mode: "next",
              day: day,
              cls: c,
            };
          }
          continue;
        }
        return {
          mode: "next",
          day: day,
          cls: c,
        };
      }
    }

    return null;
  }

  function clearHighlights(root) {
    eachNode(root.querySelectorAll(".clubworx-timetable-class--highlight"), function (el) {
      el.classList.remove("clubworx-timetable-class--highlight");
    });
  }

  function applyHighlight(root, cfg, result) {
    clearHighlights(root);
    if (!result || !result.cls) {
      return;
    }
    var c = result.cls;
    var sel =
      '.clubworx-timetable-class[data-day="' +
      result.day +
      '"][data-category="' +
      c.categoryClass +
      '"][data-start-minutes="' +
      c.startMinutes +
      '"]';
    var row = root.querySelector(sel);
    if (row) {
      row.classList.add("clubworx-timetable-class--highlight");
    }
  }

  function updateBanner(root, cfg, result) {
    var banner = root.querySelector(".clubworx-timetable-now-banner");
    if (!banner || !cfg.showNowBanner) {
      return;
    }
    var labelEl = banner.querySelector(".clubworx-timetable-now-label");
    var detailEl = banner.querySelector(".clubworx-timetable-now-detail");
    if (!labelEl || !detailEl) {
      return;
    }

    if (!result) {
      labelEl.textContent = "";
      detailEl.textContent = "No classes in schedule.";
      return;
    }

    var labels = cfg.dayLabels || {};
    var dayTitle = labels[result.day] || result.day;
    var name = result.cls.name || "";
    var timeStr = result.cls.time || "";

    if (result.mode === "now") {
      labelEl.textContent = "Happening now";
      detailEl.textContent = name + " · " + timeStr + " · " + dayTitle;
    } else {
      labelEl.textContent = "Up next";
      var clock = getVenueClock(new Date(), cfg.timezone);
      var sameDay = result.day === clock.daySlug;
      var when = sameDay ? timeStr + " · today" : timeStr + " · " + dayTitle;
      detailEl.textContent = name + " · " + when;
    }
  }

  function setupFilters(root, cfg) {
    function getFilterButton(target) {
      var el = target;
      while (el && el !== toolbar) {
        if (el.classList && el.classList.contains("clubworx-timetable-filter")) {
          return el;
        }
        el = el.parentNode;
      }
      return null;
    }

    if (!cfg.showFilters) {
      return;
    }
    var toolbar = root.querySelector(".clubworx-timetable-filters");
    if (!toolbar) {
      return;
    }

    var activeCategories = null;

    function applyFilterState() {
      var allMode = activeCategories === null;
      eachNode(root.querySelectorAll(".clubworx-timetable-class"), function (row) {
        var cat = row.getAttribute("data-category") || "";
        var show = allMode || !!activeCategories[cat];
        row.style.display = show ? "" : "none";
        row.setAttribute("aria-hidden", show ? "false" : "true");
      });

      eachNode(root.querySelectorAll(".clubworx-timetable-day"), function (col) {
        var any = false;
        eachNode(col.querySelectorAll(".clubworx-timetable-class"), function (row) {
          if (row.style.display !== "none") {
            any = true;
          }
        });
        var emptyNote = col.querySelector(".clubworx-timetable-day-empty");
        if (emptyNote) {
          emptyNote.hidden = !!any;
        }
      });
    }

    function syncButtons() {
      eachNode(toolbar.querySelectorAll(".clubworx-timetable-filter"), function (b) {
        var cat = b.getAttribute("data-category") || "all";
        var on =
          (cat === "all" && activeCategories === null) ||
          (cat !== "all" && activeCategories && activeCategories[cat]);
        b.classList.toggle("is-active", !!on);
        b.setAttribute("aria-pressed", on ? "true" : "false");
      });
    }

    toolbar.addEventListener("click", function (e) {
      var btn = getFilterButton(e.target);
      if (!btn || !toolbar.contains(btn)) {
        return;
      }
      var cat = btn.getAttribute("data-category") || "all";

      if (cat === "all") {
        activeCategories = null;
        syncButtons();
        applyFilterState();
        return;
      }

      if (activeCategories === null) {
        activeCategories = {};
      }

      if (activeCategories[cat]) {
        delete activeCategories[cat];
        if (Object.keys(activeCategories).length === 0) {
          activeCategories = null;
        }
      } else {
        activeCategories[cat] = true;
      }

      syncButtons();
      applyFilterState();
    });

    syncButtons();
    applyFilterState();
  }

  function tick(root, cfg) {
    if (!cfg.showNowBanner) {
      return;
    }
    try {
      var clock = getVenueClock(new Date(), cfg.timezone);
      var result = findNowAndNext(cfg, clock);
      updateBanner(root, cfg, result);
      applyHighlight(root, cfg, result);
    } catch (e) {
      updateBanner(root, cfg, null);
      clearHighlights(root);
    }
  }

  function initRoot(root) {
    var cfg = parseConfigScript(root);
    var showFilters = !!getCfgValue(cfg, "showFilters", !!root.querySelector(".clubworx-timetable-filters"));
    var showNowBanner = !!getCfgValue(cfg, "showNowBanner", !!root.querySelector(".clubworx-timetable-now-banner"));
    var classesByDay = getCfgValue(cfg, "classesByDay", null);
    if (!classesByDay || typeof classesByDay !== "object") {
      classesByDay = buildClassesByDayFromDom(root);
    }
    var runtimeCfg = {
      showFilters: showFilters,
      showNowBanner: showNowBanner,
      timezone: getCfgValue(cfg, "timezone", "Australia/Sydney"),
      durationMinutes: getCfgValue(cfg, "durationMinutes", 60),
      dayLabels: getCfgValue(cfg, "dayLabels", {}),
      classesByDay: classesByDay,
    };

    setupFilters(root, runtimeCfg);

    if (runtimeCfg.showNowBanner) {
      tick(root, runtimeCfg);
      setInterval(function () {
        tick(root, runtimeCfg);
      }, 45000);
      document.addEventListener("visibilitychange", function () {
        if (!document.hidden) {
          tick(root, runtimeCfg);
        }
      });
    }
  }

  function boot() {
    eachNode(document.querySelectorAll(".clubworx-timetable"), initRoot);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
