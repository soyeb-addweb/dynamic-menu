/**
 * ec-dynamic-menu-elementor-v3.js
 * EC Dynamic Menu (Elementor Nav Menu v3+ compatible)
 */
(function ($) {
  'use strict';

  console.log('ec-dynamic-menu-elementor-v3 loaded');

  // ---------- Utilities ----------
  function parseHref(href) {
    var a = document.createElement('a');
    a.href = href;
    var origin = a.protocol && a.host ? (a.protocol + '//' + a.host) : window.location.origin;
    var pathname = a.pathname || '/';
    var parts = pathname.split('/').filter(Boolean);
    var trailingSlash = /\/$/.test(pathname);
    return {
      origin: origin,
      pathname: pathname,
      parts: parts,
      trailingSlash: trailingSlash,
      search: a.search || '',
      hash: a.hash || ''
    };
  }

  function cityFromCityLink(href) {
    var p = parseHref(href);
    if (!p.parts.length) return null;
    return p.parts[p.parts.length - 1];
  }

  function rewritePracticeHrefForCity(href, citySlug) {
    var p = parseHref(href);
    if (p.parts.length < 2) return href;
    p.parts[p.parts.length - 2] = citySlug;
    var newPath = "/" + p.parts.join("/") + (p.trailingSlash ? "/" : "");
    var abs = /^https?:\/\//i.test(href);
    return (abs ? p.origin : "") + newPath + p.search + p.hash;
  }

  function titleCase(str) {
    return (str || '')
      .replace(/[-_]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .toLowerCase()
      .replace(/\b[a-z]/g, function (c) { return c.toUpperCase(); });
  }

  // ---------- Finders for Elementor structure ----------
  function $areasTitle() {
    return $('#menu-item-areas-we-serve, .menu-item-areas-we-serve').first();
  }
  function $practiceTitle() {
    return $('#menu-item-practice-areas, .menu-item-practice-areas').first();
  }
  function $practiceTitleText() {
    var $t = $practiceTitle();
    return $t.find('.e-n-menu-title-text').length ? $t.find('.e-n-menu-title-text') : $t.find('> a');
  }
  function $areasContentRoot() {
    var $t = $areasTitle();
    if (!$t.length) return $();
    var $item = $t.closest('.e-n-menu-item');
    var $content = $item.find('.e-n-menu-content');
    if ($content.length) return $content;
    var $submenu = $item.find('.sub-menu');
    return $submenu.length ? $submenu : $();
  }
  function $practiceContentRoot() {
    var $t = $practiceTitle();
    if (!$t.length) return $();
    var $item = $t.closest('.e-n-menu-item');
    var $content = $item.find('.e-n-menu-content');
    if ($content.length) return $content;
    var $submenu = $item.find('.sub-menu');
    return $submenu.length ? $submenu : $();
  }

  // ---------- Core behaviors ----------
  function applyCity(citySlug, cityName) {
    if (!citySlug) return;

    var label = (cityName && cityName.trim()) ? cityName : titleCase(citySlug);
    var $txt = $practiceTitleText();
    if ($txt.length) {
      $txt.text(label.toUpperCase() + ' PRACTICE AREAS');
    }

    var $root = $practiceContentRoot();
    $root.find('a[href]').each(function () {
      var $a = $(this);
      var href = $a.attr('href');
      if (!href || /^#|^(mailto|tel):/i.test(href)) return;
      $a.attr('href', rewritePracticeHrefForCity(href, citySlug));
    });
  }

  function bindCityClicks() {
    var $root = $areasContentRoot();
    if (!$root.length) return false;

    $root.off('click.ecCity').on('click.ecCity', 'a[href]', function (e) {
      var $a = $(this);
      var href = $a.attr('href') || '';
      var citySlug = cityFromCityLink(href);
      var cityName = $.trim($a.text());

      if (!citySlug) return;

      try {
        sessionStorage.setItem('currentCitySlug', citySlug);
        sessionStorage.setItem('currentCityName', cityName);
      } catch (err) {}

      // keep navigation by default. If you want to block navigation and just update menus, uncomment:
      // e.preventDefault();

      applyCity(citySlug, cityName);
    });

    return true;
  }

  function applyCityFromStorageOrUrl() {
    var slug = null, name = null;
    try {
      slug = sessionStorage.getItem('currentCitySlug');
      name = sessionStorage.getItem('currentCityName');
    } catch (err) {}

    if (!slug) {
      var parts = window.location.pathname.split('/').filter(Boolean);
      if (parts.length >= 1) {
        slug = parts.length >= 2 ? parts[parts.length - 2] : parts[parts.length - 1];
      }
    }

    if (slug) applyCity(slug, name);
  }

  // ---------- Init flow ----------
  var booted = false;
  function tryInit() {
    var ok = $areasTitle().length && $practiceTitle().length;
    if (!ok) return false;
    if (!booted) {
      booted = true;
      bindCityClicks();
      applyCityFromStorageOrUrl();
      console.log('ec-dynamic-menu-elementor-v3 initialized');
    }
    return true;
  }

  function start() {
    tryInit();

    $(window).on('elementor/frontend/init', function () {
      if (window.elementorFrontend && window.elementorFrontend.hooks) {
        elementorFrontend.hooks.addAction('frontend/element_ready/global', tryInit);
        elementorFrontend.hooks.addAction('frontend/element_ready/nav-menu.default', tryInit);
      }
      setTimeout(tryInit, 50);
      setTimeout(tryInit, 250);
      setTimeout(tryInit, 1000);
    });

    var mo = new MutationObserver(function () { tryInit(); });
    mo.observe(document.body, { childList: true, subtree: true });

    $(function () { tryInit(); });
    $(window).on('load', function () { tryInit(); });
  }

  start();
})(jQuery);
