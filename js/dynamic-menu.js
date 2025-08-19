/**
 * Unified Dynamic Practice Areas Menu JavaScript
 * Works with Elementor v2 and v3+ (auto-detects menu structure)
 */
(function ($) {
    'use strict';

    // ---------- Utilities ----------
    function parseHref(href) {
        var a = document.createElement('a');
        a.href = href || '';
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
        var newPath = '/' + p.parts.join('/') + (p.trailingSlash ? '/' : '');
        var abs = /^https?:\/\//i.test(href || '');
        return (abs ? p.origin : '') + newPath + p.search + p.hash;
    }

    function titleCase(str) {
        return (str || '')
            .replace(/[-_]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase()
            .replace(/\b[a-z]/g, function (c) { return c.toUpperCase(); });
    }

    // ---------- Menu selectors (auto-detect v2/v3) ----------
    function detectMenuSelectors() {
        var usingElementorMegaMenu = $('.e-n-menu').length > 0;
        var usesV3Classes = $('.menu-item-areas-we-serve, .menu-item-practice-areas').length > 0;
        if (usingElementorMegaMenu || usesV3Classes) {
            return {
                isV3: true,
                areas_we_serve: '#menu-item-areas-we-serve, .menu-item-areas-we-serve',
                practice_areas: '#menu-item-practice-areas, .menu-item-practice-areas',
                practice_title_text: '.e-n-menu-title-text'
            };
        }
        return {
            isV3: false,
            areas_we_serve: (window.dynamicMenuData && dynamicMenuData.menu_selectors) ? dynamicMenuData.menu_selectors.areas_we_serve : '.menu-item-areas-we-serve',
            practice_areas: (window.dynamicMenuData && dynamicMenuData.menu_selectors) ? dynamicMenuData.menu_selectors.practice_areas : '.menu-item-practice-areas',
            practice_title_text: 'a'
        };
    }

    // ---------- Finders for Elementor v3 structure ----------
    function $areasTitle() { return $('#menu-item-areas-we-serve, .menu-item-areas-we-serve').first(); }
    function $practiceTitle() { return $('#menu-item-practice-areas, .menu-item-practice-areas').first(); }
    function $practiceTitleText() {
        var $t = $practiceTitle();
        var $txt = $t.find('.e-n-menu-title-text');
        return $txt.length ? $txt : $t.find('> a');
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
    function getPracticeAreasContentContainer() {
        var $practiceAreasMenu = $('#menu-item-practice-areas, .menu-item-practice-areas');
        if (!$practiceAreasMenu.length) $practiceAreasMenu = $('.e-n-menu-item:contains("PRACTICE AREAS")');
        if (!$practiceAreasMenu.length && window.dynamicMenuData && dynamicMenuData.menu_selectors) {
            $practiceAreasMenu = $(dynamicMenuData.menu_selectors.practice_areas);
        }
        var $item = $practiceAreasMenu.closest('.e-n-menu-item').length ? $practiceAreasMenu.closest('.e-n-menu-item') : $practiceAreasMenu;
        var $contentContainer = $item.find('.e-n-menu-content').first();
        if (!$contentContainer.length) $contentContainer = $practiceAreasMenu.find('.e-con-inner');
        if (!$contentContainer.length) $contentContainer = $('#pamenu');
        if (!$contentContainer.length) $contentContainer = $item.find('.e-con-inner, .elementor-widget-container').first();
        return $contentContainer;
    }

    // ---------- URL context helpers ----------
    function getUrlSegments() {
        return window.location.pathname.replace(/^\/+|\/+$/g, '').split('/').filter(Boolean);
    }
    function getUrlContext() {
        var segments = getUrlSegments();
        var stateLayerEnabled = window.dynamicMenuData && dynamicMenuData.state_layer_enabled === 'yes';
        var defaultState = (window.dynamicMenuData && dynamicMenuData.default_state) || '';
        var stateSlug = '';
        var citySlug = '';
        var practiceAreaSlug = '';
        if (stateLayerEnabled) {
            if (segments.length >= 2) {
                stateSlug = segments[0] || defaultState;
                citySlug = segments[1] || (dynamicMenuData && dynamicMenuData.default_city) || '';
                practiceAreaSlug = segments[2] || '';
            } else {
                stateSlug = defaultState;
                citySlug = segments[0] || (dynamicMenuData && dynamicMenuData.default_city) || '';
            }
        } else {
            citySlug = segments[0] || (dynamicMenuData && dynamicMenuData.default_city) || '';
            practiceAreaSlug = segments[1] || '';
        }
        return { stateSlug: stateSlug, citySlug: citySlug, practiceAreaSlug: practiceAreaSlug };
    }

    function getCityNameBySlug(citySlug) {
        if (!citySlug || !window.dynamicMenuData || !dynamicMenuData.city_pages) return '';
        for (var i = 0; i < dynamicMenuData.city_pages.length; i++) {
            if (dynamicMenuData.city_pages[i].slug === citySlug) return dynamicMenuData.city_pages[i].title;
        }
        return '';
    }

    // ---------- v2 flows ----------
    function updateStandardPracticeAreasMenu(response) {
        if (!window.dynamicMenuData || !dynamicMenuData.menu_selectors) return;
        var $practiceAreasMenu = $(dynamicMenuData.menu_selectors.practice_areas);
        var targetCity = response.city_slug;
        if ($practiceAreasMenu.data('ecPaCity') === targetCity) return;
        var cityDisplayName = response.city_anchor_text || response.city_name;
        var menuText = cityDisplayName + ' Practice Areas';
        if (dynamicMenuData.uppercase_menu === 'yes') menuText = menuText.toUpperCase();
        $practiceAreasMenu.find('> a').text(menuText);
        var $subMenu = $practiceAreasMenu.find('.sub-menu');
        if ($subMenu.length === 0) {
            $subMenu = $('<ul class="sub-menu elementor-nav-menu--dropdown"></ul>');
            $practiceAreasMenu.append($subMenu);
            $practiceAreasMenu.addClass('menu-item-has-children');
        } else {
            $subMenu.empty();
        }
        response.practice_areas.forEach(function (practiceArea) {
            $subMenu.append('<li class="menu-item"><a href="' + practiceArea.url + '" class="elementor-sub-item">' + (practiceArea.anchor_text || practiceArea.title) + '</a></li>');
        });
        $practiceAreasMenu.data('ecPaCity', targetCity);
        $practiceAreasMenu.find('> a').attr('href', '#');
    }
    function updatePracticeAreasMenu(citySlug, stateSlug) {
        $.ajax({
            url: dynamicMenuData.ajaxurl,
            method: 'GET',
            data: { city_slug: citySlug, state_slug: stateSlug || '' },
            beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce); },
            success: function (response) {
                if (response && response.success && response.practice_areas && response.practice_areas.length) {
                    updateStandardPracticeAreasMenu(response);
                }
            }
        });
    }

    function updatePracticeAreasWidget(citySlug, cityName, stateSlug) {
        var $widgets = $(dynamicMenuData.widget_selector);
        if (!$widgets.length) return;
        $.ajax({
            url: dynamicMenuData.ajaxurl,
            method: 'GET',
            data: { city_slug: citySlug, state_slug: stateSlug || '' },
            beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce); },
            success: function (response) {
                if (response && response.success && response.practice_areas && response.practice_areas.length) {
                    $widgets.each(function () {
                        var $widget = $(this);
                        var isElementor = $widget.closest(dynamicMenuData.elementor_selector).length > 0;
                        var $widgetTitle = isElementor ? $widget.closest(dynamicMenuData.elementor_selector).find('.practice-areas-title') : $widget.prev('h2.widget-title');
                        if (!$widgetTitle || !$widgetTitle.length) $widgetTitle = $widget.find('.practice-areas-title');
                        if ($widgetTitle && $widgetTitle.length) {
                            var displayName = response.city_anchor_text || response.city_name || cityName || titleCase(citySlug);
                            var titleText = displayName + ' Practice Areas';
                            if (dynamicMenuData.uppercase_menu === 'yes') titleText = titleText.toUpperCase();
                            $widgetTitle.text(titleText).css('visibility', 'visible');
                        }
                        var $list = $widget.find('.practice-areas-list');
                        $list.empty();
                        response.practice_areas.forEach(function (pa) {
                            $list.append('<li class="practice-area-item"><a href="' + pa.url + '" ' + (pa.anchor_text ? 'class="using-anchor-text"' : '') + '>' + (pa.anchor_text || pa.title) + '</a></li>');
                        });
                        $widget.addClass('content-loaded');
                    });
                }
            }
        });
    }

    // ---------- v3 flows ----------
    function updateElementorPracticeAreasMenu(response) {
        var $practiceAreasMenu = $('#menu-item-practice-areas, .menu-item-practice-areas');
        if (!$practiceAreasMenu.length) $practiceAreasMenu = $('.e-n-menu-item:contains("PRACTICE AREAS")');
        if (!$practiceAreasMenu.length && window.dynamicMenuData && dynamicMenuData.menu_selectors) {
            $practiceAreasMenu = $(dynamicMenuData.menu_selectors.practice_areas);
        }
        if (!$practiceAreasMenu.length) return false;
        var cityDisplayName = response.city_anchor_text || response.city_name;
        var menuText = cityDisplayName + ' Practice Areas';
        if (dynamicMenuData.uppercase_menu === 'yes') menuText = menuText.toUpperCase();
        $practiceAreasMenu.find('.e-n-menu-title-text, > a').text(menuText);
        var isMobileMenu = $practiceAreasMenu.closest('.elementor-nav-menu--dropdown').length > 0;
        if (isMobileMenu) {
            var $subMenu = $practiceAreasMenu.find('.sub-menu');
            if ($subMenu.length) {
                $subMenu.empty();
                response.practice_areas.forEach(function (practiceArea) {
                    $subMenu.append('<li class="menu-item"><a href="' + practiceArea.url + '" class="elementor-sub-item">' + (practiceArea.anchor_text || practiceArea.title) + '</a></li>');
                });
            }
            return true;
        }
        var $contentContainer = getPracticeAreasContentContainer();
        if (!$contentContainer.length) return false;
        var targetCity = response.city_slug;
        if ($contentContainer.data('ecPaCity') === targetCity && $contentContainer.find('a[href]').length) return true;
        $contentContainer.empty();
        var $grid = $('<div class="elementor-element e-grid e-con-full e-con e-child"></div>');
        var $list = $('<div class="elementor-element elementor-icon-list--layout-traditional elementor-list-item-link-full_width elementor-widget elementor-widget-icon-list"><div class="elementor-widget-container"><ul class="elementor-icon-list-items"></ul></div></div>');
        var $itemsList = $list.find('.elementor-icon-list-items');
        response.practice_areas.forEach(function (practiceArea) {
            $itemsList.append('<li class="elementor-icon-list-item"><a href="' + practiceArea.url + '"><span class="elementor-icon-list-text">' + (practiceArea.anchor_text || practiceArea.title) + '</span></a></li>');
        });
        $grid.append($list);
        $contentContainer.append($grid);
        $contentContainer.data('ecPaCity', targetCity);
        $practiceAreasMenu.find('.e-n-menu-title-container, > a').attr('href', '#');
        return true;
    }

    function ensureV3PracticeAreasPopulated(citySlug) {
        var selectors = detectMenuSelectors();
        if (!selectors.isV3) return;
        var $container = getPracticeAreasContentContainer();
        if (!$container.length) return;
        if ($container.data('ecPaCity') === citySlug && $container.find('a[href]').length) return;
        // Prevent duplicate in-flight fetches
        if ($container.data('ecFetch') === citySlug) return;
        $container.data('ecFetch', citySlug);
        var ctx = getUrlContext();
        $.ajax({
            url: dynamicMenuData.ajaxurl,
            method: 'GET',
            data: { city_slug: citySlug, state_slug: ctx.stateSlug || '' },
            beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce); },
            success: function (response) {
                if (response && response.success) updateElementorPracticeAreasMenu(response);
                $container.data('ecFetch', null);
            },
            error: function(){
                $container.data('ecFetch', null);
            }
        });
    }

    function applyCityAndPopulate(citySlug, cityName) {
        if (!citySlug) return;
        try { sessionStorage.setItem('currentCitySlug', citySlug); } catch (e) {}
        try { sessionStorage.setItem('currentCityName', cityName || ''); } catch (e) {}
        var label = (cityName && cityName.trim()) ? cityName : titleCase(citySlug);
        var menuLabel = label + ' Practice Areas';
        if (window.dynamicMenuData && dynamicMenuData.uppercase_menu === 'yes') menuLabel = menuLabel.toUpperCase();
        var $txt = $practiceTitleText();
        if ($txt.length) $txt.text(menuLabel);
        var $root = $practiceContentRoot();
        $root.find('a[href]').each(function () {
            var $a = $(this);
            var href = $a.attr('href');
            if (!href || /^#|^(mailto|tel):/i.test(href)) return;
            $a.attr('href', rewritePracticeHrefForCity(href, citySlug));
        });
        ensureV3PracticeAreasPopulated(citySlug);
        var ctx = getUrlContext();
        updatePracticeAreasWidget(citySlug, label, ctx.stateSlug || '');
    }

    // ---------- Init / Event Binding ----------
    function init() {
        var selectors = detectMenuSelectors();
        var isV3 = !!selectors.isV3;

        var storedSlug = null, storedName = null;
        try { storedSlug = sessionStorage.getItem('currentCitySlug'); storedName = sessionStorage.getItem('currentCityName'); } catch (e) {}
        var ctx = getUrlContext();
        var chosenSlug = storedSlug || ctx.citySlug || (dynamicMenuData && dynamicMenuData.default_city) || '';
        var chosenName = storedName || getCityNameBySlug(chosenSlug);

        if (isV3) {
            // Apply once and one retry to catch late mounts (no loops)
            if (chosenSlug) applyCityAndPopulate(chosenSlug, chosenName);
            setTimeout(function () { if (chosenSlug) applyCityAndPopulate(chosenSlug, chosenName); }, 250);

            // Bind city clicks in Areas We Serve
            $(document).off('click.ecV3AreasCity');
            $(document).on('click.ecV3AreasCity', (selectors.areas_we_serve + ' a'), function () {
                var href = $(this).attr('href') || '';
                var citySlug = cityFromCityLink(href);
                var cityName = $.trim($(this).text());
                if (!citySlug) return;
                applyCityAndPopulate(citySlug, cityName);
            });
        } else {
            // v2 behavior
            if (chosenSlug) {
                updatePracticeAreasMenu(chosenSlug, ctx.stateSlug || '');
                updatePracticeAreasWidget(chosenSlug, chosenName || titleCase(chosenSlug), ctx.stateSlug || '');
            }
            var usingElementorMegaMenu = $('.e-n-menu').length > 0;
            if (usingElementorMegaMenu) {
                $(document).off('click.ecAreasCity').on('click.ecAreasCity', (selectors.areas_we_serve + ' .elementor-icon-list-items a'), function () {
                    var cityUrl = $(this).attr('href') || '';
                    var citySlug = cityUrl.split('/').filter(Boolean).pop();
                    var cityName = $(this).text();
                    try { sessionStorage.setItem('currentCitySlug', citySlug); } catch (e) {}
                    try { sessionStorage.setItem('currentCityName', cityName); } catch (e) {}
                });
            } else {
                $(document).off('click.ecAreasCity').on('click.ecAreasCity', (selectors.areas_we_serve + ' .sub-menu a'), function () {
                    var cityUrl = $(this).attr('href') || '';
                    var citySlug = cityUrl.split('/').filter(Boolean).pop();
                    var cityName = $(this).text();
                    try { sessionStorage.setItem('currentCitySlug', citySlug); } catch (e) {}
                    try { sessionStorage.setItem('currentCityName', cityName); } catch (e) {}
                });
            }
        }
    }

    $(function () { init(); });

})(jQuery);
/**
 * Unified Dynamic Practice Areas Menu JavaScript
 * Works with Elementor v2 and v3+ (auto-detects menu structure)
 */
(function ($) {
    'use strict';

    // ---------- Utilities ----------
    function parseHref(href) {
        var a = document.createElement('a');
        a.href = href || '';
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
        var newPath = '/' + p.parts.join('/') + (p.trailingSlash ? '/' : '');
        var abs = /^https?:\/\//i.test(href || '');
        return (abs ? p.origin : '') + newPath + p.search + p.hash;
    }

    function titleCase(str) {
        return (str || '')
            .replace(/[-_]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase()
            .replace(/\b[a-z]/g, function (c) { return c.toUpperCase(); });
    }

    // ---------- Menu selectors (auto-detect v2/v3) ----------
    function detectMenuSelectors() {
        var usesV3Classes = $('.menu-item-areas-we-serve, .menu-item-practice-areas').length > 0;
        var usingElementorMegaMenu = $('.e-n-menu').length > 0;

        if (usesV3Classes || usingElementorMegaMenu) {
            return {
                isV3: true,
                areas_we_serve: '#menu-item-areas-we-serve, .menu-item-areas-we-serve',
                practice_areas: '#menu-item-practice-areas, .menu-item-practice-areas',
                practice_title_text: '.e-n-menu-title-text'
            };
        } else {
            return {
                isV3: false,
                areas_we_serve: dynamicMenuData.menu_selectors.areas_we_serve,
                practice_areas: dynamicMenuData.menu_selectors.practice_areas,
                practice_title_text: 'a'
            };
        }
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
        var $txt = $t.find('.e-n-menu-title-text');
        if ($txt.length) return $txt;
        return $t.find('> a');
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
    function getPracticeAreasContentContainer() {
        var $practiceAreasMenu = $('#menu-item-practice-areas, .menu-item-practice-areas');
        if (!$practiceAreasMenu.length) {
            $practiceAreasMenu = $('.e-n-menu-item:contains("PRACTICE AREAS")');
        }
        if (!$practiceAreasMenu.length) {
            $practiceAreasMenu = $(dynamicMenuData.menu_selectors.practice_areas);
        }
        var $item = $practiceAreasMenu.closest('.e-n-menu-item').length ? $practiceAreasMenu.closest('.e-n-menu-item') : $practiceAreasMenu;
        var $contentContainer = $item.find('.e-n-menu-content').first();
        if (!$contentContainer.length) {
            $contentContainer = $practiceAreasMenu.find('.e-con-inner');
        }
        if (!$contentContainer.length) {
            $contentContainer = $('#pamenu');
        }
        if (!$contentContainer.length) {
            $contentContainer = $item.find('.e-con-inner, .elementor-widget-container').first();
        }
        return $contentContainer;
    }

    // ---------- URL context helpers ----------
    function getUrlSegments() {
        return window.location.pathname.replace(/^\/+|\/+$/g, '').split('/').filter(Boolean);
    }
    function getUrlContext() {
        var segments = getUrlSegments();
        var stateLayerEnabled = dynamicMenuData.state_layer_enabled === 'yes';
        var defaultState = dynamicMenuData.default_state || '';
        var stateSlug = '';
        var citySlug = '';
        var practiceAreaSlug = '';
        var subPracticeAreaSlug = '';
        if (stateLayerEnabled) {
            if (segments.length >= 3) {
                stateSlug = segments[0];
                citySlug = segments[1];
                practiceAreaSlug = segments[2];
                subPracticeAreaSlug = segments[3] || '';
            } else {
                stateSlug = defaultState;
                citySlug = segments[0] || dynamicMenuData.default_city;
                practiceAreaSlug = segments[1] || '';
            }
        } else {
            citySlug = segments[0] || dynamicMenuData.default_city;
            practiceAreaSlug = segments[1] || '';
            subPracticeAreaSlug = segments[2] || '';
        }
        return { stateSlug: stateSlug, citySlug: citySlug, practiceAreaSlug: practiceAreaSlug, subPracticeAreaSlug: subPracticeAreaSlug };
    }

    function getCityNameBySlug(citySlug) {
        if (!citySlug) return '';
        var cityName = '';
        if (window.dynamicMenuData && dynamicMenuData.city_pages) {
            dynamicMenuData.city_pages.forEach(function (city) {
                if (city.slug === citySlug) {
                    cityName = city.title;
                }
            });
        }
        return cityName;
    }

    // ---------- v2 paths ----------
    function updateStandardPracticeAreasMenu(response) {
        var $practiceAreasMenu = $(dynamicMenuData.menu_selectors.practice_areas);

        var targetCity = response.city_slug;
        if ($practiceAreasMenu.data('ecPaCity') === targetCity) {
            return;
        }

        var cityDisplayName = response.city_anchor_text || response.city_name;
        var menuText = cityDisplayName + ' Practice Areas';
        if (dynamicMenuData.uppercase_menu === 'yes') {
            menuText = menuText.toUpperCase();
        }
        $practiceAreasMenu.find('> a').text(menuText);

        var $subMenu = $practiceAreasMenu.find('.sub-menu');
        if ($subMenu.length === 0) {
            var submenuId = 'sm-' + Math.floor(Math.random() * 100000000) + '-2';
            var linkId = submenuId.replace('-2', '-1');
            var $link = $practiceAreasMenu.find('> a');
            $link.addClass('has-submenu');
            $link.attr({
                'id': linkId,
                'aria-haspopup': 'true',
                'aria-controls': submenuId,
                'aria-expanded': 'false'
            });
            if (!$link.find('.sub-arrow').length) {
                $link.append('<span class="sub-arrow"><i class="fas fa-caret-down"></i></span>');
            }
            $subMenu = $('<ul class="sub-menu elementor-nav-menu--dropdown"></ul>');
            $subMenu.attr({
                'id': submenuId,
                'role': 'group',
                'aria-hidden': 'true',
                'aria-labelledby': linkId,
                'aria-expanded': 'false'
            });
            $practiceAreasMenu.append($subMenu);
            $practiceAreasMenu.addClass('menu-item-has-children');
        } else {
            $subMenu.empty();
        }

        response.practice_areas.forEach(function (practiceArea) {
            $subMenu.append(
                '<li class="menu-item">' +
                '<a href="' + practiceArea.url + '" class="elementor-sub-item">' +
                (practiceArea.anchor_text || practiceArea.title) +
                '</a>' +
                '</li>'
            );
        });

        $practiceAreasMenu.data('ecPaCity', targetCity);
        $practiceAreasMenu.find('> a').attr('href', '#');
    }

    function updatePracticeAreasMenu(citySlug, stateSlug) {
        $.ajax({
            url: dynamicMenuData.ajaxurl,
            method: 'GET',
            data: { city_slug: citySlug, state_slug: stateSlug || '' },
            beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce); },
            success: function (response) {
                if (response && response.success && response.practice_areas && response.practice_areas.length) {
                    updateStandardPracticeAreasMenu(response);
                }
            }
        });
    }

    // ---------- v3 paths ----------
    function updateElementorPracticeAreasMenu(response) {
        // Find the practice areas container in the Elementor menu
        var $practiceAreasMenu = $('#menu-item-practice-areas, .menu-item-practice-areas');
        if (!$practiceAreasMenu.length) {
            $practiceAreasMenu = $('.e-n-menu-item:contains("PRACTICE AREAS")');
        }
        if (!$practiceAreasMenu.length) {
            $practiceAreasMenu = $(dynamicMenuData.menu_selectors.practice_areas);
        }
        if (!$practiceAreasMenu.length) {
            return false;
        }

        var cityDisplayName = response.city_anchor_text || response.city_name;
        var menuText = cityDisplayName + ' Practice Areas';
        if (dynamicMenuData.uppercase_menu === 'yes') {
            menuText = menuText.toUpperCase();
        }
        $practiceAreasMenu.find('.e-n-menu-title-text, > a').text(menuText);

        var isMobileMenu = $practiceAreasMenu.closest('.elementor-nav-menu--dropdown').length > 0;
        if (isMobileMenu) {
            var $subMenu = $practiceAreasMenu.find('.sub-menu');
            if ($subMenu.length) {
                $subMenu.empty();
                response.practice_areas.forEach(function (practiceArea) {
                    $subMenu.append(
                        '<li class="menu-item">' +
                        '<a href="' + practiceArea.url + '" class="elementor-sub-item">' +
                        (practiceArea.anchor_text || practiceArea.title) +
                        '</a>' +
                        '</li>'
                    );
                });
            }
            return true;
        }

        var $contentContainer = getPracticeAreasContentContainer();
        if (!$contentContainer.length) {
            return false;
        }

        var targetCity = response.city_slug;
        if ($contentContainer.data('ecPaCity') === targetCity) {
            return true;
        }

        $contentContainer.empty();
        var $grid = $('<div class="elementor-element e-grid e-con-full e-con e-child"></div>');
        var $list = $('<div class="elementor-element elementor-icon-list--layout-traditional elementor-list-item-link-full_width elementor-widget elementor-widget-icon-list"><div class="elementor-widget-container"><ul class="elementor-icon-list-items"></ul></div></div>');
        var $itemsList = $list.find('.elementor-icon-list-items');

        response.practice_areas.forEach(function (practiceArea) {
            $itemsList.append(
                '<li class="elementor-icon-list-item">' +
                '<a href="' + practiceArea.url + '">' +
                '<span class="elementor-icon-list-text">' +
                (practiceArea.anchor_text || practiceArea.title) +
                '</span></a>' +
                '</li>'
            );
        });

        $grid.append($list);
        $contentContainer.append($grid);
        $contentContainer.data('ecPaCity', targetCity);
        $practiceAreasMenu.find('.e-n-menu-title-container, > a').attr('href', '#');
        return true;
    }

    function updatePracticeAreasWidget(citySlug, cityName, stateSlug) {
        var $widgets = $(dynamicMenuData.widget_selector);
        if (!$widgets.length) return;
        $.ajax({
            url: dynamicMenuData.ajaxurl,
            method: 'GET',
            data: { city_slug: citySlug, state_slug: stateSlug || '' },
            beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce); },
            success: function (response) {
                if (response && response.success && response.practice_areas && response.practice_areas.length) {
                    $widgets.each(function () {
                        var $widget = $(this);
                        var isElementor = $widget.closest(dynamicMenuData.elementor_selector).length > 0;
                        var $widgetTitle = isElementor ? $widget.closest(dynamicMenuData.elementor_selector).find('.practice-areas-title') : $widget.prev('h2.widget-title');
                        if (!$widgetTitle || !$widgetTitle.length) {
                            $widgetTitle = $widget.find('.practice-areas-title');
                        }
                        if ($widgetTitle && $widgetTitle.length) {
                            var displayName = response.city_anchor_text || response.city_name || cityName || titleCase(citySlug);
                            var titleText = displayName + ' Practice Areas';
                            if (dynamicMenuData.uppercase_menu === 'yes') titleText = titleText.toUpperCase();
                            $widgetTitle.text(titleText).css('visibility', 'visible');
                        }
                        var $list = $widget.find('.practice-areas-list');
                        $list.empty();
                        response.practice_areas.forEach(function (pa) {
                            $list.append('<li class="practice-area-item"><a href="' + pa.url + '" ' + (pa.anchor_text ? 'class="using-anchor-text"' : '') + '>' + (pa.anchor_text || pa.title) + '</a></li>');
                        });
                        $widget.addClass('content-loaded');
                    });
                }
            }
        });
    }

    function ensureV3PracticeAreasPopulated(citySlug) {
        var selectors = detectMenuSelectors();
        if (!selectors.isV3) return;
        var $container = getPracticeAreasContentContainer();
        if ($container.length && $container.data('ecPaCity') === citySlug && $container.find('a[href]').length > 0) {
            return;
        }
        var ctx = getUrlContext();
        $.ajax({
            url: dynamicMenuData.ajaxurl,
            method: 'GET',
            data: { city_slug: citySlug, state_slug: ctx.stateSlug || '' },
            beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce); },
            success: function (response) {
                if (response && response.success) {
                    updateElementorPracticeAreasMenu(response);
                }
            }
        });
    }

    function applyCityAndPopulate(citySlug, cityName) {
        if (!citySlug) return;
        try { sessionStorage.setItem('currentCitySlug', citySlug); } catch (e) {}
        try { sessionStorage.setItem('currentCityName', cityName || ''); } catch (e) {}
        var label = (cityName && cityName.trim()) ? cityName : titleCase(citySlug);
        var menuLabel = label + ' Practice Areas';
        if (dynamicMenuData.uppercase_menu === 'yes') menuLabel = menuLabel.toUpperCase();
        var $txt = $practiceTitleText();
        if ($txt.length) $txt.text(menuLabel);
        var $root = $practiceContentRoot();
        $root.find('a[href]').each(function () {
            var $a = $(this);
            var href = $a.attr('href');
            if (!href || /^#|^(mailto|tel):/i.test(href)) return;
            $a.attr('href', rewritePracticeHrefForCity(href, citySlug));
        });
        ensureV3PracticeAreasPopulated(citySlug);
        var ctx = getUrlContext();
        updatePracticeAreasWidget(citySlug, cityName || label, ctx.stateSlug || '');
    }

    // ---------- Init / Event Binding ----------
    function tryInitV3() {
        var selectors = detectMenuSelectors();
        if (!selectors.isV3) return false;
        var $areasRoot = $areasContentRoot();
        $areasRoot.off('click.ecCity').on('click.ecCity', 'a[href]', function () {
            var $a = $(this);
            var href = $a.attr('href') || '';
            var citySlug = cityFromCityLink(href);
            var cityName = $.trim($a.text());
            if (!citySlug) return;
            applyCityAndPopulate(citySlug, cityName);
        });
        var storedSlug = null, storedName = null;
        try { storedSlug = sessionStorage.getItem('currentCitySlug'); storedName = sessionStorage.getItem('currentCityName'); } catch (e) {}
        var ctx = getUrlContext();
        var slug = storedSlug || ctx.citySlug || dynamicMenuData.default_city || '';
        var name = storedName || getCityNameBySlug(slug);
        if (slug) applyCityAndPopulate(slug, name);
        return true;
    }

    $(document).ready(function () {
        // Hide related widget titles initially
        $('.dynamic-related-locations-widget, .widget_dynamic_related_locations_widget').each(function () {
            var $widget = $(this);
            var $title = $widget.find('.related-locations-title');
            if (!$title.length) $title = $widget.prev('h2.widget-title');
            if ($title.length) $title.css('visibility', 'hidden');
        });

        var selectors = detectMenuSelectors();
        var isV3 = !!selectors.isV3;

        if (isV3) {
            tryInitV3();
        } else {
            // v2 flow
            var ctx = getUrlContext();
            if (ctx.citySlug) {
                updatePracticeAreasMenu(ctx.citySlug, ctx.stateSlug);
                updatePracticeAreasWidget(ctx.citySlug, getCityNameBySlug(ctx.citySlug), ctx.stateSlug);
            } else if (dynamicMenuData.default_city) {
                updatePracticeAreasMenu(dynamicMenuData.default_city, dynamicMenuData.default_state || '');
                updatePracticeAreasWidget(dynamicMenuData.default_city, getCityNameBySlug(dynamicMenuData.default_city), dynamicMenuData.default_state || '');
            }

            // v2 click bindings
            var usingElementorMegaMenu = $('.e-n-menu').length > 0;
            if (usingElementorMegaMenu) {
                $(document).off('click.ecAreasCity').on('click.ecAreasCity', selectors.areas_we_serve + ' .elementor-icon-list-items a', function () {
                    var cityUrl = $(this).attr('href') || '';
                    var citySlug = cityUrl.split('/').filter(Boolean).pop();
                    var cityName = $(this).text();
                    try { sessionStorage.setItem('currentCitySlug', citySlug); } catch (e) {}
                    try { sessionStorage.setItem('currentCityName', cityName); } catch (e) {}
                });
            } else {
                $(document).off('click.ecAreasCity').on('click.ecAreasCity', selectors.areas_we_serve + ' .sub-menu a', function () {
                    var cityUrl = $(this).attr('href') || '';
                    var citySlug = cityUrl.split('/').filter(Boolean).pop();
                    var cityName = $(this).text();
                    try { sessionStorage.setItem('currentCitySlug', citySlug); } catch (e) {}
                    try { sessionStorage.setItem('currentCityName', cityName); } catch (e) {}
                });
            }
        }
    });

    $(window).on('elementor/frontend/init', function () {
        var selectors = detectMenuSelectors();
        if (selectors.isV3) {
            tryInitV3();
            if (window.elementorFrontend && window.elementorFrontend.hooks) {
                try {
                    elementorFrontend.hooks.addAction('frontend/element_ready/global', tryInitV3);
                    elementorFrontend.hooks.addAction('frontend/element_ready/nav-menu.default', tryInitV3);
                } catch (e) {}
            }
            setTimeout(tryInitV3, 50);
            setTimeout(tryInitV3, 250);
            setTimeout(tryInitV3, 1000);
        }
    });

    try {
        var mo = new MutationObserver(function () { tryInitV3(); });
        mo.observe(document.body, { childList: true, subtree: true });
    } catch (e) {}

})(jQuery);