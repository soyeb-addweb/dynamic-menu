/**
 * Dynamic Practice Areas Menu JavaScript
 * Updated to support anchor text field, Elementor Mega Menu, uppercase setting, default city,
 * and sub-practice areas display
 */
(function ($) {
    'use strict';

    // Store original practice areas menu for restoring when needed
    var originalPracticeAreasMenu = null;
    var currentCitySlug = null;
    var currentCityName = null;
    var currentStateSlug = null;
    var currentPracticeAreaSlug = null;
    // --- Elementor v3 helpers (from stable reference) ---
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
        var isV3 = $('.menu-item-areas-we-serve, .menu-item-practice-areas').length > 0;
        var usingElementorMegaMenu = $('.e-n-menu').length > 0;

        if (isV3) {
            return {
                areas_we_serve: '#menu-item-areas-we-serve, .menu-item-areas-we-serve',
                practice_areas: '#menu-item-practice-areas, .menu-item-practice-areas',
                practice_title_text: '.e-n-menu-title-text'
            };
        } else {
            return {
                areas_we_serve: usingElementorMegaMenu ?
                    '#menu-item-areas-we-serve, .e-n-menu-item:contains("AREAS WE SERVE")' :
                    dynamicMenuData.menu_selectors.areas_we_serve,
                practice_areas: usingElementorMegaMenu ?
                    '#menu-item-practice-areas, .e-n-menu-item:contains("PRACTICE AREAS")' :
                    dynamicMenuData.menu_selectors.practice_areas,
                practice_title_text: 'a'
            };
        }
    }
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
    function applyCity(citySlug, cityName) {
        if (!citySlug) return;
        var label = (cityName && cityName.trim()) ? cityName : titleCase(citySlug);
        var menuLabel = label + ' Practice Areas';
        if (window.dynamicMenuData && dynamicMenuData.uppercase_menu === 'yes') {
            menuLabel = menuLabel.toUpperCase();
        }
        var $txt = $practiceTitleText();
        if ($txt.length) {
            $txt.text(menuLabel);
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
        $root.off('click.ecCity').on('click.ecCity', 'a[href]', function () {
            var $a = $(this);
            var href = $a.attr('href') || '';
            var citySlug = cityFromCityLink(href);
            var cityName = $.trim($a.text());
            if (!citySlug) return;
            try {
                sessionStorage.setItem('currentCitySlug', citySlug);
                sessionStorage.setItem('currentCityName', cityName);
            } catch (err) {}
            applyCity(citySlug, cityName);
        });
        return true;
    }
    var ecV3Booted = false;
    function tryInitV3() {
        var ok = $areasTitle().length && $practiceTitle().length;
        if (!ok) return false;
        if (!ecV3Booted) {
            ecV3Booted = true;
            bindCityClicks();
            // apply from storage or URL
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
            if (slug) applyCity(slug, name || getCityNameBySlug(slug));
        }
        return true;
    }
    var ecDynamicMenuRefreshTimer = null;
    var lastRelatedWidgetKey = null;
    var lastDetectionKey = null;
    var lastMenuCitySlug = null;
    var lastMenuStateSlug = null;
    var bootedDynamicMenu = false;
    var isUpdatingPracticeAreasMenu = false;

    function scheduleDynamicMenuRefresh() {
        if (ecDynamicMenuRefreshTimer) {
            clearTimeout(ecDynamicMenuRefreshTimer);
        }
        ecDynamicMenuRefreshTimer = setTimeout(function () {
            try {
                detectCurrentPage();
                updateRelatedLocationsWidget();
            } catch (err) {
                if (window.console && console.error) {
                    console.error('Dynamic menu refresh error', err);
                }
            }
        }, 400);
    }

    $(document).ready(function () {
        // ↓ NEW: pull state-layer toggle & default
        var stateLayerEnabled = dynamicMenuData.state_layer_enabled === 'yes';
        var defaultState = dynamicMenuData.default_state || '';

        // Hide titles initially
        $('.dynamic-related-locations-widget, .widget_dynamic_related_locations_widget').each(function () {
            var $widget = $(this);
            var $title = $widget.find('.related-locations-title');
            if (!$title.length) {
                $title = $widget.prev('h2.widget-title');
            }
            if ($title.length) {
                $title.css('visibility', 'hidden');
            }
        });

        // Auto-detect selectors for v2/v3
        var menuSelectors = detectMenuSelectors();
        var usingElementorMegaMenu = $('.e-n-menu').length > 0;


        // Cache DOM elements
        var $areasWeServeMenu = $(menuSelectors.areas_we_serve).first();
        var $practiceAreasMenu = $(menuSelectors.practice_areas).first();
        var $practiceAreasWidget = $(dynamicMenuData.widget_selector);


        // Store original practice areas menu for restoring later
        if ($practiceAreasMenu.length) {
            originalPracticeAreasMenu = $practiceAreasMenu.clone();
        }

        // Detect current page and update menu if it's a city or practice area page
        detectCurrentPage();

        // Call the function to update related locations widget
        updateRelatedLocationsWidget();

        // If no city is active but we have a default city, use it
        if (!currentCitySlug && dynamicMenuData.default_city) {
            loadDefaultCityPracticeAreas(dynamicMenuData.default_city);
        }

        // Handle city page link clicks (works for v2 and v3)
        $(document)
            .off('click.ecAreasCity')
            .on('click.ecAreasCity', menuSelectors.areas_we_serve + ' a', function () {
                var cityUrl = $(this).attr('href') || '';
                var citySlug = cityFromCityLink(cityUrl);
                var cityName = $(this).text();
                if (!citySlug) return;
                try {
                    sessionStorage.setItem('currentCitySlug', citySlug);
                    sessionStorage.setItem('currentCityName', cityName);
                } catch (e) {}
                // Apply immediate v3 link rewrite if present
                applyCity(citySlug, cityName);
            });

        // Convert practice areas menu item to a dropdown menu (v2 only)
        if (!usingElementorMegaMenu) {
            $practiceAreasMenu.each(function () {
                var $this = $(this);
                if (!$this.hasClass('menu-item-has-children')) {
                    $this.addClass('menu-item-has-children');
                }
            });
        }

        // Check if we're in the mobile menu context
        var $mobileMenu = $('.elementor-nav-menu--dropdown');
        if ($mobileMenu.length) {
            // Find the Practice Areas menu item in the mobile menu
            var $mobilePracticeAreasMenu = $mobileMenu.find('.menu-item-practice-areas');

            if ($mobilePracticeAreasMenu.length) {
                // Transform it into a dropdown if it's not already
                if (!$mobilePracticeAreasMenu.hasClass('menu-item-has-children')) {

                    // Add necessary classes
                    $mobilePracticeAreasMenu.addClass('menu-item-has-children');

                    // Get the menu link
                    var $link = $mobilePracticeAreasMenu.find('> a');

                    if ($link.length) {
                        $link.addClass('has-submenu');
                        // $link.append('<span class="sub-arrow"><i class="fas fa-caret-down"></i></span>');

                        // Create a submenu element
                        var submenuHtml = '<ul class="sub-menu elementor-nav-menu--dropdown">' +
                            '<li class="menu-item"><a href="#" class="elementor-sub-item">Loading practice areas...</a></li>' +
                            '</ul>';

                        // Append it directly after the link
                        $link.after(submenuHtml);

                        // Set ARIA attributes after submenu is added
                        $link.attr({
                            'aria-haspopup': 'true',
                            'aria-expanded': 'false'
                        });
                    }
                }
            }
        }

        // Handle hover on practice areas menu for non-city pages (v2 only)
        if (!usingElementorMegaMenu) {
            $(document).off('mouseenter.ecDynamicPA', menuSelectors.practice_areas).on('mouseenter.ecDynamicPA', menuSelectors.practice_areas, function () {
                if (currentCitySlug) {
                    return;
                }
                restoreOriginalPracticeAreasMenu();
            });
        }

        // Add this CSS to your theme or Elementor custom CSS (inject once)
        if (!document.getElementById('ec-dynamic-menu-styles')) {
            var styleTag = document.createElement('style');
            styleTag.id = 'ec-dynamic-menu-styles';
            styleTag.textContent = `
                /* .elementor-nav-menu--dropdown .menu-item-practice-areas.menu-item-has-children > .sub-menu {
                    display: none;
                } */
                .elementor-nav-menu--dropdown .menu-item-practice-areas.menu-item-has-children > a[aria-expanded="true"] + .sub-menu {
                    display: block !important;
                }
            `;
            document.head.appendChild(styleTag);
        }

        // Use event delegation to handle clicks on the Practice Areas menu item (li or a) (v2 mobile only)
        $(document).off('click.ecMobilePA', '.elementor-nav-menu--dropdown .menu-item-practice-areas').on('click.ecMobilePA', '.elementor-nav-menu--dropdown .menu-item-practice-areas', function (e) {
            // If clicking a submenu link, let it proceed normally
            if ($(e.target).closest('.sub-menu').length) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();

            var $li = $(this);
            var $link = $li.find('> a');
            var $subMenu = $link.next('.sub-menu');

            if (!$subMenu.length) return;

            var citySlug = currentCitySlug || dynamicMenuData.default_city || '';

            if (citySlug) {
                if (!$li.data('loaded')) {
                    $.ajax({
                        url: dynamicMenuData.ajaxurl,
                        method: 'GET',
                        data: {city_slug: citySlug},
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce);
                        },
                        success: function (response) {
                            if (response.success && response.practice_areas && response.practice_areas.length > 0) {
                                var menuItems = '';
                                response.practice_areas.forEach(function (area) {
                                    menuItems += '<li class="menu-item">' +
                                        '<a href="' + area.url + '" class="elementor-sub-item">' +
                                        (area.anchor_text || area.title) +
                                        '</a></li>';
                                });
                                $subMenu.html(menuItems);
                                $li.data('loaded', true);

                                // ✅ OPEN submenu *after* data loads
                                $subMenu.stop(true, true).slideDown(200);
                                $link.attr('aria-expanded', 'true');
                            } else {
                                $subMenu.html('<li class="menu-item"><a href="#" class="elementor-sub-item">No practice areas found</a></li>');
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('AJAX error', error);
                            $subMenu.html('<li class="menu-item"><a href="#" class="elementor-sub-item">Error loading practice areas</a></li>');
                        }
                    });
                } else {
                    // Already loaded, just toggle normally
                    if ($subMenu.is(':visible')) {
                        $link.attr('aria-expanded', 'false');
                        $subMenu.slideUp(200);
                    } else {
                        $link.attr('aria-expanded', 'true');
                        $subMenu.slideDown(200);
                    }
                }
            }
        });
    });

    // Elementor v3+ lifecycle hooks: align with stable reference tryInitV3
    $(window).on('elementor/frontend/init', function () {
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            try {
                elementorFrontend.hooks.addAction('frontend/element_ready/global', tryInitV3);
                elementorFrontend.hooks.addAction('frontend/element_ready/nav-menu.default', tryInitV3);
            } catch (e) {}
        }
        setTimeout(tryInitV3, 50);
        setTimeout(tryInitV3, 250);
        setTimeout(tryInitV3, 1000);
    });

    $(function () { tryInitV3(); });
    $(window).on('load', function () { tryInitV3(); });
    try {
        var ecV3Mo = new MutationObserver(function () { tryInitV3(); });
        ecV3Mo.observe(document.body, { childList: true, subtree: true });
    } catch (e) {}

    /**
     * Load practice areas for the default city
     */
    function loadDefaultCityPracticeAreas(citySlug) {
        if (!citySlug) return;

        // Find city name for the slug
        var cityName = "Default City";
        if (dynamicMenuData.city_pages && dynamicMenuData.city_pages.length) {
            dynamicMenuData.city_pages.forEach(function (city) {
                if (city.slug === citySlug) {
                    cityName = city.title;
                }
            });
        }

        // Update menus with the default city, passing default state slug if available
        updatePracticeAreasMenu(citySlug, dynamicMenuData.default_state || '');
        updatePracticeAreasWidget(citySlug, cityName, dynamicMenuData.default_state || '');
    }

    /**
     * Detect the current page and update menus accordingly
     */
    function detectCurrentPage() {
        // ↓ NEW: pull state-layer toggle & default
        var stateLayerEnabled = dynamicMenuData.state_layer_enabled === 'yes';
        var defaultState = dynamicMenuData.default_state || '';

        // normalize & split into segments
        var segments = window.location.pathname
            .replace(/^\/|\/$/g, '')  // strip leading/trailing slash
            .split('/')
            .filter(Boolean);

        // initialize slugs
        var stateSlug = '';
        var citySlug = '';
        var practiceAreaSlug = '';
        var subPracticeAreaSlug = '';

        // ↑ NEW: decide which segment is which
        if (stateLayerEnabled) {
            if (segments.length >= 3) {
                stateSlug = segments[0];
                citySlug = segments[1];
                practiceAreaSlug = segments[2];
                subPracticeAreaSlug = segments[3] || '';
            } else {
                // fallback to default state + 2-segment logic
                stateSlug = defaultState;
                citySlug = segments[0] || dynamicMenuData.default_city;
                practiceAreaSlug = segments[1] || '';
            }
        } else {
            citySlug = segments[0] || dynamicMenuData.default_city;
            practiceAreaSlug = segments[1] || '';
            subPracticeAreaSlug = segments[2] || '';
        }

        // stored context
        var storedCitySlug = sessionStorage.getItem('currentCitySlug');
        var storedCityName = sessionStorage.getItem('currentCityName');

        // detect page-type
        var isCityPage = false;
        var isPracticeAreaPage = false;
        var isSubPracticeAreaPage = false;
        var cityName = '';

        // 1) Is this one of our city pages?
        if (dynamicMenuData.city_pages && dynamicMenuData.city_pages.length) {
            dynamicMenuData.city_pages.forEach(function (cityPage) {
                if (cityPage.slug === citySlug) {
                    isCityPage = true;
                    cityName = cityPage.title;
                    // store context
                    sessionStorage.setItem('currentCitySlug', citySlug);
                    sessionStorage.setItem('currentCityName', cityName);
                    return;
                }
            });
        }

        // 2) Practice area / sub-practice area?
        if (practiceAreaSlug) {
            isPracticeAreaPage = true;
            window.currentPracticeAreaSlug = practiceAreaSlug;
        }
        if (subPracticeAreaSlug) {
            isSubPracticeAreaPage = true;
        }

        // Build a detection key to avoid repeated work
        var detectionKey = [stateSlug, citySlug, practiceAreaSlug, subPracticeAreaSlug, isCityPage, isPracticeAreaPage, isSubPracticeAreaPage].join('|');
        if (detectionKey === lastDetectionKey) {
            return; // no change in context
        }
        lastDetectionKey = detectionKey;

        // --- BRANCH A: we found a city in the URL ---
        if (isCityPage) {
            // update the left-hand menu
            updatePracticeAreasMenu(citySlug, stateSlug);

            if (isPracticeAreaPage) {
                // if there's a nested sub-practice, show those
                updatePracticeAreasWidgetForSubPracticeAreas(
                    citySlug,
                    practiceAreaSlug,
                    cityName,
                    stateSlug
                );
            } else {
                // just a city page, show all practice-areas
                updatePracticeAreasWidget(
                    citySlug,
                    cityName,
                    stateSlug
                );
            }

            // --- BRANCH B: no city in URL but we have stored context ---
        } else if (storedCitySlug) {
            var isPracticeAreaPath = (
                segments.length >= 2 &&
                segments[0] === storedCitySlug
            );

            if (isPracticeAreaPath) {
                // keep the old city context
                updatePracticeAreasMenu(storedCitySlug, /*stateSlug?*/ '');

                if (segments.length >= 3) {
                    // e.g. /{city}/{practice}/{subPractice}
                    updatePracticeAreasWidgetForSubPracticeAreas(
                        storedCitySlug,
                        segments[1],
                        storedCityName,
                        /*stateSlug?*/ ''
                    );
                } else {
                    // e.g. /{city}/{practice}
                    updatePracticeAreasWidgetForSubPracticeAreas(
                        storedCitySlug,
                        segments[1],
                        storedCityName,
                        /*stateSlug?*/ ''
                    );
                }
            } else {
                // unrelated page → clear context + restore defaults
                sessionStorage.removeItem('currentCitySlug');
                sessionStorage.removeItem('currentCityName');
                restoreOriginalPracticeAreasMenu();

                if (dynamicMenuData.default_city) {
                    loadDefaultCityPracticeAreas(dynamicMenuData.default_city);
                } else {
                    restoreOriginalPracticeAreasWidget();
                }
            }

            // --- BRANCH C: no city context at all ---
        } else {
            restoreOriginalPracticeAreasMenu();

            if (dynamicMenuData.default_city) {
                loadDefaultCityPracticeAreas(dynamicMenuData.default_city);
            } else {
                restoreOriginalPracticeAreasWidget();
            }
        }
    }

    /**
     * Update the Practice Areas Widget to show sub-practice areas
     */
    function updatePracticeAreasWidgetForSubPracticeAreas(citySlug, practiceAreaSlug, cityName, stateSlug) {
        var $widgets = $(dynamicMenuData.widget_selector);

        if (!$widgets.length) {
            return; // No widget on the page
        }

        // First, try to get the practice area page ID
        $.ajax({
            url: dynamicMenuData.ajaxurl,
            method: 'GET',
            data: {
                city_slug: citySlug,
                state_slug: stateSlug
            },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce);
            },
            success: function (response) {
                if (response.success && response.practice_areas.length > 0) {
                    // Find the current practice area in the response
                    var currentPracticeArea = null;
                    response.practice_areas.forEach(function (practiceArea) {
                        if (practiceArea.slug === practiceAreaSlug) {
                            currentPracticeArea = practiceArea;
                        }
                    });

                    if (currentPracticeArea) {
                        // Now get sub-practice areas for this practice area
                        getSubPracticeAreas(currentPracticeArea.id, citySlug, cityName, practiceAreaSlug, stateSlug, $widgets);
                    } else {
                        // Fall back to showing regular practice areas
                        updatePracticeAreasWidget(citySlug, cityName, stateSlug);
                    }
                } else {
                    // No practice areas found, fall back to regular widget update
                    updatePracticeAreasWidget(citySlug, cityName, stateSlug);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error fetching practice areas:', error);
                // Fall back to regular widget update
                updatePracticeAreasWidget(citySlug, cityName, stateSlug);
            }
        });
    }

    /**
     * Get sub-practice areas for a practice area
     */
    function getSubPracticeAreas(practiceAreaId, citySlug, cityName, practiceAreaSlug, stateSlug, $widgets) {

        // Get current page path to identify which page we're on
        var currentPath = window.location.pathname;
        var currentPathSegments = currentPath.split('/').filter(Boolean);
        var currentSubPageSlug = currentPathSegments.length >= 3 ? currentPathSegments[2] : '';

        // Use the REST API endpoint
        $.ajax({
            url: dynamicMenuData.ajaxurl.replace('get-practice-areas', 'get-sub-practice-areas'),
            method: 'GET',
            data: {
                practice_area_id: practiceAreaId,
                city_slug: citySlug,
                state_slug: stateSlug,
                practice_area_slug: practiceAreaSlug
            },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce);
            },
            success: function (response) {

                if (response.success && response.sub_practice_areas && response.sub_practice_areas.length > 0) {
                    // Update widgets with sub-practice areas
                    $widgets.each(function () {
                        var $widget = $(this);
                        var isElementor = $widget.closest(dynamicMenuData.elementor_selector).length > 0;

                        // Update widget title if present
                        var $widgetTitle;
                        if (isElementor) {
                            $widgetTitle = $widget.closest(dynamicMenuData.elementor_selector).find('.practice-areas-title');
                        } else {
                            $widgetTitle = $widget.prev('h2.widget-title');
                            if (!$widgetTitle.length) {
                                $widgetTitle = $widget.find('.practice-areas-title');
                            }
                        }

                        if ($widgetTitle.length) {
                            var practiceAreaTitle = response.practice_area_title || practiceAreaSlug;
                            var widgetTitle = practiceAreaTitle + ' Resources';

                            // Apply uppercase if setting is enabled
                            if (dynamicMenuData.uppercase_menu === 'yes') {
                                widgetTitle = widgetTitle.toUpperCase();
                            }

                            $widgetTitle.text(widgetTitle);
                            // Make sure title is visible
                            $widgetTitle.css('visibility', 'visible');
                        }

                        // Clear loading message and populate list
                        var $list = $widget.find('.practice-areas-list');
                        $list.empty();

                        // Filter out current page and add sub-practice area items to widget
                        var filteredSubPracticeAreas = response.sub_practice_areas.filter(function (subPracticeArea) {
                            return subPracticeArea.slug !== currentSubPageSlug;
                        });

                        // Only show widget if we have sub-practice areas to display after filtering
                        if (filteredSubPracticeAreas.length > 0) {
                            filteredSubPracticeAreas.forEach(function (subPracticeArea) {
                                $list.append(
                                    '<li class="practice-area-item">' +
                                    '<a href="' + subPracticeArea.url + '" class="' + (subPracticeArea.anchor_text ? 'using-anchor-text' : '') + '">' +
                                    (subPracticeArea.anchor_text || subPracticeArea.title) +
                                    '</a>' +
                                    '</li>'
                                );
                            });
                        } else if (currentSubPageSlug) {
                            // We're on a sub-practice area page with no other siblings to show
                            // Fall back to showing practice areas for the city
                            updatePracticeAreasWidget(citySlug, cityName);
                        } else {
                            $list.html('<li class="no-practice-areas">No additional resources available</li>');
                        }

                        // Add class to indicate content is loaded
                        $widget.addClass('content-loaded');
                    });
                } else {
                    // No sub-practice areas found, fall back to showing regular practice areas
                    updatePracticeAreasWidget(citySlug, cityName, stateSlug);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error fetching sub-practice areas:', error);
                // Fall back to showing regular practice areas
                updatePracticeAreasWidget(citySlug, cityName, stateSlug);
            }
        });
    }

    /**
     * Update the Practice Areas menu based on the selected city (and optional state)
     *
     * @param {string} citySlug
     * @param {string} stateSlug
     */
    function updatePracticeAreasMenu(citySlug, stateSlug) {
        // Don’t re-run if nothing changed
        if ((citySlug === currentCitySlug && stateSlug === currentStateSlug) ||
            (citySlug === lastMenuCitySlug && stateSlug === lastMenuStateSlug) ||
            isUpdatingPracticeAreasMenu) {
            return;
        }

        // store the new context
        currentCitySlug = citySlug;
        currentStateSlug = stateSlug || '';
        lastMenuCitySlug = citySlug;
        lastMenuStateSlug = stateSlug || '';

        // Detect whether we’re using the Elementor mega-menu
        var usingElementorMegaMenu = $('.e-n-menu').length > 0;

        // For Elementor v3 mega-menu, use stable applyCity rewriting instead of rebuilding via AJAX
        if (usingElementorMegaMenu) {
            applyCity(citySlug, getCityNameBySlug(citySlug));
            return;
        }

        // Fetch the practice areas from WP REST for standard menus
        isUpdatingPracticeAreasMenu = true;
        $.ajax({
            url: dynamicMenuData.ajaxurl,   // get-practice-areas endpoint
            method: 'GET',
            data: {
                city_slug: citySlug,
                state_slug: stateSlug      // ← NEW: include state
            },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce);
            },
            success: function (response) {
                if (response.success && response.practice_areas.length > 0) {
                    updateStandardPracticeAreasMenu(response);
                } else {
                    // fallback: clear or restore if no practice areas found
                    restoreOriginalPracticeAreasMenu();
                }
                isUpdatingPracticeAreasMenu = false;
            },
            error: function (xhr, status, error) {
                console.error('Error fetching practice areas:', error);
                restoreOriginalPracticeAreasMenu();
                isUpdatingPracticeAreasMenu = false;
            }
        });
    }

    /**
     * Update standard WordPress menu for practice areas
     */
    function updateStandardPracticeAreasMenu(response) {
        // Update practice areas menu with city-specific practice areas
        var $practiceAreasMenu = $(dynamicMenuData.menu_selectors.practice_areas);

        // Skip if menu already reflects this city
        var targetCity = response.city_slug || currentCitySlug;
        if ($practiceAreasMenu.data('ecPaCity') === targetCity) {
            return;
        }

        // Use city anchor text if available
        var cityDisplayName = response.city_anchor_text || response.city_name;
        var menuText = cityDisplayName + ' Practice Areas';

        // Apply uppercase if setting is enabled
        if (dynamicMenuData.uppercase_menu === 'yes') {
            menuText = menuText.toUpperCase();
        }

        $practiceAreasMenu.find('> a').text(menuText);

        // Create sub-menu if it doesn't exist
        var $subMenu = $practiceAreasMenu.find('.sub-menu');
        if ($subMenu.length === 0) {
            // Create submenu with proper attributes
            var submenuId = 'sm-' + Math.floor(Math.random() * 100000000) + '-2';
            var linkId = submenuId.replace('-2', '-1');

            // Update the link attributes
            var $link = $practiceAreasMenu.find('> a');
            $link.addClass('has-submenu');
            $link.attr({
                'id': linkId,
                'aria-haspopup': 'true',
                'aria-controls': submenuId,
                'aria-expanded': 'false'
            });

            // Add dropdown arrow if it doesn't exist
            if (!$link.find('.sub-arrow').length) {
                $link.append('<span class="sub-arrow"><i class="fas fa-caret-down"></i></span>');
            }

            // Add the submenu
            $subMenu = $('<ul class="sub-menu elementor-nav-menu--dropdown"></ul>');
            $subMenu.attr({
                'id': submenuId,
                'role': 'group',
                'aria-hidden': 'true',
                'aria-labelledby': linkId,
                'aria-expanded': 'false'
            });

            $practiceAreasMenu.append($subMenu);

            // Make sure the parent has needed class
            $practiceAreasMenu.addClass('menu-item-has-children');
        } else {
            $subMenu.empty();
        }

        // Add practice area items to sub-menu using anchor text if available
        response.practice_areas.forEach(function (practiceArea) {
            $subMenu.append(
                '<li class="menu-item">' +
                '<a href="' + practiceArea.url + '" class="elementor-sub-item">' +
                (practiceArea.anchor_text || practiceArea.title) +
                '</a>' +
                '</li>'
            );
        });

        // Mark as rendered for this city
        $practiceAreasMenu.data('ecPaCity', targetCity);

        // Make the practice areas menu clickable
        $practiceAreasMenu.find('> a').attr('href', '#');
    }

    /**
     * Update Elementor Mega Menu for practice areas
     */
    function updateElementorPracticeAreasMenu(response) {
        // Find the practice areas container in the Elementor menu
        var $practiceAreasMenu = $('.e-n-menu-item:contains("PRACTICE AREAS")');

        // Also look for the mobile menu version
        if (!$practiceAreasMenu.length) {
            $practiceAreasMenu = $(dynamicMenuData.menu_selectors.practice_areas);
        }

        if (!$practiceAreasMenu.length) {
            console.error('Practice Areas menu not found in Elementor Mega Menu');
            return;
        }

        // Use city anchor text if available
        var cityDisplayName = response.city_anchor_text || response.city_name;
        var menuText = cityDisplayName + ' Practice Areas';

        // Apply uppercase if setting is enabled
        if (dynamicMenuData.uppercase_menu === 'yes') {
            menuText = menuText.toUpperCase();
        }

        // Update the title
        $practiceAreasMenu.find('.e-n-menu-title-text, > a').text(menuText);

        // Check if we're in the mobile menu
        var isMobileMenu = $practiceAreasMenu.closest('.elementor-nav-menu--dropdown').length > 0;

        if (isMobileMenu) {
            // Handle mobile menu updating
            var $subMenu = $practiceAreasMenu.find('.sub-menu');

            if ($subMenu.length) {
                $subMenu.empty();

                // Add practice area items to sub-menu using anchor text if available
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

            return;
        }

        // Desktop menu updates below
        // Prefer Elementor v3 mega menu content container first
        var $item = $practiceAreasMenu.closest('.e-n-menu-item').length ? $practiceAreasMenu.closest('.e-n-menu-item') : $practiceAreasMenu;
        var $contentContainer = $item.find('.e-n-menu-content').first();

        if (!$contentContainer.length) {
            // Fallbacks for older layouts or custom containers
            $contentContainer = $practiceAreasMenu.find('.e-con-inner');
        }
        if (!$contentContainer.length) {
            $contentContainer = $('#pamenu');
        }
        if (!$contentContainer.length) {
            console.error('Practice Areas content container not found');
            return;
        }

        // Skip heavy rebuild if already rendered for this city
        var targetCity = response.city_slug || currentCitySlug;
        if ($contentContainer.data('ecPaCity') === targetCity) {
            return;
        }

        // Clear existing content
        $contentContainer.empty();

        // Create grid and columns for practice areas
        var $grid = $('<div class="elementor-element e-grid e-con-full e-con e-child"></div>');
        var $list = $('<div class="elementor-element elementor-icon-list--layout-traditional elementor-list-item-link-full_width elementor-widget elementor-widget-icon-list"><div class="elementor-widget-container"><ul class="elementor-icon-list-items"></ul></div></div>');

        // Add practice area items to list using anchor text if available
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

        // Mark container as rendered for this city to avoid churn
        $contentContainer.data('ecPaCity', targetCity);

        // Make the practice areas menu clickable
        $practiceAreasMenu.find('.e-n-menu-title-container, > a').attr('href', '#');
    }

    /**
     * Restore the original practice areas menu
     */
    function restoreOriginalPracticeAreasMenu() {
        var usingElementorMegaMenu = $('.e-n-menu').length > 0;

        if (usingElementorMegaMenu) {
            // Restore Elementor mega menu title
            var $practiceAreasMenu = $('.e-n-menu-item:contains("PRACTICE AREAS")');
            if ($practiceAreasMenu.length) {
                $practiceAreasMenu.find('.e-n-menu-title-text').text('PRACTICE AREAS');
            }
        } else if (originalPracticeAreasMenu) {
            // Get current mobile menu structure
            var $currentMenu = $(dynamicMenuData.menu_selectors.practice_areas);
            var isMobileMenu = $currentMenu.closest('.elementor-nav-menu--dropdown').length > 0;

            if (isMobileMenu) {
                // In mobile view, just update the text but preserve structure
                $currentMenu.find('> a').text('Practice Areas');

                // Clear out submenu items but keep the structure
                var $subMenu = $currentMenu.find('.sub-menu');
                if ($subMenu.length) {
                    $subMenu.empty();
                    $subMenu.append('<li class="menu-item"><a href="#" class="elementor-sub-item">Please select a city</a></li>');
                }
            } else {
                // In desktop view, do a full replacement
                var $practiceAreasMenu = $(dynamicMenuData.menu_selectors.practice_areas);
                $practiceAreasMenu.replaceWith(originalPracticeAreasMenu.clone());
            }
        }

        currentCitySlug = null;
        currentCityName = null;
        lastMenuCitySlug = null;
        lastMenuStateSlug = null;
    }

    /**
     * Update the Practice Areas widget based on the selected city (and optional state)
     *
     * @param {string} citySlug   – the city slug
     * @param {string} cityName   – the city’s display name (for “No areas found” messaging)
     * @param {string} stateSlug  – the state slug (empty if none)
     */
    function updatePracticeAreasWidget(citySlug, cityName, stateSlug) {
        var $widgets = $(dynamicMenuData.widget_selector);
        if (!$widgets.length) {
            return; // nothing to do
        }

        $.ajax({
            url: dynamicMenuData.ajaxurl,  // your get-practice-areas endpoint
            method: 'GET',
            data: {
                city_slug: citySlug,
                state_slug: stateSlug           // ← NEW: pass the state along
            },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce);
            },
            success: function (response) {
                if (response.success && response.practice_areas.length > 0) {
                    $widgets.each(function () {
                        var $widget = $(this);
                        var isElementor = $widget.closest(dynamicMenuData.elementor_selector).length > 0;

                        // Find or generate the widget title element
                        var $widgetTitle;
                        if (isElementor) {
                            $widgetTitle = $widget.closest(dynamicMenuData.elementor_selector)
                                .find('.practice-areas-title');
                        } else {
                            $widgetTitle = $widget.prev('h2.widget-title');
                            if (!$widgetTitle.length) {
                                $widgetTitle = $widget.find('.practice-areas-title');
                            }
                        }

                        // Update the title
                        if ($widgetTitle.length) {
                            var cityDisplayName = response.city_anchor_text || response.city_name || cityName;
                            var titleText = cityDisplayName + ' Practice Areas';

                            if (dynamicMenuData.uppercase_menu === 'yes') {
                                titleText = titleText.toUpperCase();
                            }
                            $widgetTitle.text(titleText)
                                .css('visibility', 'visible');
                        }

                        // Populate the list
                        var $list = $widget.find('.practice-areas-list');
                        $list.empty();
                        response.practice_areas.forEach(function (pa) {
                            $list.append(
                                '<li class="practice-area-item">' +
                                '<a href="' + pa.url + '" ' +
                                (pa.anchor_text ? 'class="using-anchor-text"' : '') +
                                '>' +
                                (pa.anchor_text || pa.title) +
                                '</a>' +
                                '</li>'
                            );
                        });

                        $widget.addClass('content-loaded');
                    });

                } else {
                    // No practice areas found
                    $widgets.each(function () {
                        var $widget = $(this);
                        var $list = $widget.find('.practice-areas-list');
                        $list.html(
                            '<li class="no-practice-areas">' +
                            'No practice areas found for ' + cityName +
                            '</li>'
                        );
                    });
                }
            },
            error: function (xhr, status, error) {
                console.error('Error fetching practice areas for widget:', error);
                restoreOriginalPracticeAreasWidget();
            }
        });
    }

    /**
     * Restore the original practice areas widget
     */
    function restoreOriginalPracticeAreasWidget() {
        var $widgets = $(dynamicMenuData.widget_selector);

        if (!$widgets.length) {
            return; // No widget on the page
        }

        $widgets.each(function () {
            var $widget = $(this);
            var isElementor = $widget.closest(dynamicMenuData.elementor_selector).length > 0;

            // Reset title
            var $widgetTitle;
            if (isElementor) {
                $widgetTitle = $widget.closest(dynamicMenuData.elementor_selector).find('.practice-areas-title');
            } else {
                $widgetTitle = $widget.prev('h2.widget-title');
                if (!$widgetTitle.length) {
                    $widgetTitle = $widget.find('.practice-areas-title');
                }
            }

            if ($widgetTitle.length) {
                // Try to get original title from data attribute, or revert to "Practice Areas"
                var originalTitle = $widgetTitle.data('original-title') || 'Practice Areas';
                $widgetTitle.text(originalTitle);
                $widgetTitle.css('visibility', 'visible');
            }

            // Reset content
            var $list = $widget.find('.practice-areas-list');
            $list.html('<li class="select-city-message">Please select a city to view practice areas</li>');

            // Add class to indicate content is loaded
            $widget.addClass('content-loaded');
        });
    }

    /**
     * Update the Related Locations widget
     */
    function updateRelatedLocationsWidget() {
        var $widgets = $('.dynamic-related-locations-widget');

        if (!$widgets.length) {
            return;
        }

        // ↓ NEW: pull state-layer toggle & default
        var stateLayerEnabled = dynamicMenuData.state_layer_enabled === 'yes';
        var defaultState = dynamicMenuData.default_state || '';

        // normalize & split path into segments
        var segments = window.location.pathname
            .replace(/^\/|\/$/g, '')
            .split('/')
            .filter(Boolean);

        // initialize slugs
        var stateSlug = '';
        var citySlug = '';
        var practiceAreaSlug = '';

        // decide which segment is which
        if (stateLayerEnabled) {
            if (segments.length >= 3) {
                stateSlug = segments[0];
                citySlug = segments[1];
                practiceAreaSlug = segments[2];
            } else {
                // fallback to default state + 2-segment logic
                stateSlug = defaultState;
                citySlug = segments[0] || dynamicMenuData.default_city;
                practiceAreaSlug = segments[1] || '';
            }
        } else {
            citySlug = segments[0];
            practiceAreaSlug = segments[1];
        }

        // check if this is a valid city/practice page
        var isCityPracticePage = false;
        if (citySlug && practiceAreaSlug && dynamicMenuData.city_pages) {
            dynamicMenuData.city_pages.forEach(function (cityPage) {
                if (cityPage.slug === citySlug) {
                    isCityPracticePage = true;
                }
            });
        }

        // Build key and short-circuit if identical
        var relatedKey = [stateSlug, citySlug, practiceAreaSlug].join('|');
        if (relatedKey === lastRelatedWidgetKey) {
            return;
        }
        lastRelatedWidgetKey = relatedKey;

        // Not a city/practice page? show all or default
        if (!isCityPracticePage) {
            if (dynamicMenuData.default_city) {
                // console.log('Not on a city/practice page, using default city:', dynamicMenuData.default_city);
                showAllLocations($widgets);
            } else {
                // console.log('Not on a city/practice page and no default city set');
                showAllLocations($widgets);
            }
            return;
        }

        // Otherwise, fetch & render related locations
        $.ajax({
            url: dynamicMenuData.ajaxurl.replace('get-practice-areas', 'get-related-locations'),
            method: 'GET',
            data: {
                practice_area_slug: practiceAreaSlug,
                city_slug: citySlug,
                state_slug: stateSlug       // ← include state here
            },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce);
            },
            success: function (response) {
                $widgets.each(function () {
                    var $widget = $(this);
                    var $list = $widget.find('.related-locations-list');
                    var $title = $widget.find('.related-locations-title');

                    $list.empty();

                    if (response.related_locations && response.related_locations.length) {
                        if ($title.length) {
                            $title.css('visibility', 'visible');
                        }

                        response.related_locations.forEach(function (location) {
                            $list.append(
                                '<li class="related-location-item">' +
                                '<a href="' + location.practice_area_url + '">' +
                                location.practice_area_display_text +
                                ' In ' + location.city_display_text +
                                '</a>' +
                                '</li>'
                            );
                        });
                    } else {
                        var showEmpty = $widget.data('show-empty') !== false;
                        var emptyMessage = $widget.data('empty-message') ||
                            'No other locations offer this practice area';

                        if (showEmpty) {
                            $list.html('<li class="no-related-locations">' + emptyMessage + '</li>');
                        } else {
                            $widget.hide();
                        }
                    }
                });
            },
            error: function (xhr, status, error) {
                console.error('Error fetching related locations:', error);
            }
        });
    }

    /**
     * Show all available locations
     */

    /**
     * Show all available locations
     */
    /**
     * Show all available locations
     */
    function showAllLocations($widgets) {
        // Parse each city’s own URL for its state slug
        // Hide widget titles initially
        $widgets.each(function () {
            var $widget = $(this);
            var isElementor = $widget.closest(dynamicMenuData.related_elementor_selector).length > 0;

            // Find the title
            var $widgetTitle;
            if (isElementor) {
                $widgetTitle = $widget.closest(dynamicMenuData.related_elementor_selector).find('.related-locations-title');
            } else {
                $widgetTitle = $widget.prev('h2.widget-title');
                if (!$widgetTitle.length) {
                    $widgetTitle = $widget.find('.related-locations-title');
                }
            }

            // Hide the title initially
            if ($widgetTitle.length) {
                $widgetTitle.css('visibility', 'hidden');
                // Store original title if not already stored
                if (!$widgetTitle.data('original-title')) {
                    $widgetTitle.data('original-title', $widgetTitle.text());
                }
            }
        });

        // Get current page URL to identify current location
        var currentUrlPath = window.location.pathname;
        var currentPathSegments = currentUrlPath.split('/').filter(Boolean);
        var currentCitySlug = currentPathSegments.length >= 1 ? currentPathSegments[0] : '';

        // Use the city pages data that's already available
        var cityPages = dynamicMenuData.city_pages;

        if (cityPages && cityPages.length > 0) {
            // Filter out:
            // 1. Items with href="#"
            // 2. Items containing "Areas We Serve" in title
            // 3. The current location we're viewing
            var filteredCityPages = cityPages.filter(function (city) {
                return city.url !== '#' &&
                    city.url.indexOf('#') !== (city.url.length - 1) &&
                    !city.title.includes('Areas We Serve') &&
                    !city.title.includes('More Areas') &&
                    city.slug !== currentCitySlug; // Filter out current location
            });

            // Get all filtered city pages' data (including anchor text)
            var pagePromises = filteredCityPages.map(function (city) {
                // Determine this city’s state slug from its URL
                var cityPath = new URL(city.url).pathname.replace(/^\/|\/$/g, '');
                var citySegments = cityPath.split('/');
                var stateSlugForCity = '';
                if (dynamicMenuData.state_layer_enabled === 'yes') {
                    stateSlugForCity = citySegments.length >= 2 ? citySegments[0] : dynamicMenuData.default_state || '';
                }
                // Return a promise for each page
                return new Promise(function (resolve) {
                    // First, check if we already have anchor text from WordPress
                    // Try to fetch this information from the REST API to get the accurate anchor_text
                    $.ajax({
                        url: dynamicMenuData.ajaxurl.replace('get-practice-areas', 'get-practice-areas'),
                        method: 'GET',
                        data: {
                            city_slug: city.slug,
                            state_slug: stateSlugForCity
                        },
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', dynamicMenuData.nonce);
                        },
                        success: function (apiResponse) {
                            // If we got a valid response with city anchor text, use it
                            if (apiResponse.success && apiResponse.city_anchor_text) {
                                // console.log('Got anchor text from API for ' + city.slug + ': ' + apiResponse.city_anchor_text);

                                resolve({
                                    id: city.id,
                                    url: city.url,
                                    slug: city.slug,
                                    menuTitle: city.title,
                                    pageTitle: city.title,
                                    anchorText: apiResponse.city_anchor_text
                                });
                            } else {
                                // Fallback to scraping the page
                                fallbackToPageScraping();
                            }
                        },
                        error: function () {
                            // Fallback to scraping the page
                            fallbackToPageScraping();
                        }
                    });

                    // Fallback function to scrape the page
                    function fallbackToPageScraping() {
                        $.ajax({
                            url: city.url,
                            method: 'GET',
                            success: function (data) {
                                // Extract the page title from the HTML
                                var pageTitle = $(data).filter('title').text() ||
                                    $(data).find('title').text() ||
                                    city.title;

                                // Try multiple methods to extract anchor text
                                var anchorText = '';

                                // Method 1: Look for meta[name='anchor-text']
                                var metaMatch = data.match(/<meta[^>]*?name=["']anchor[_\-]text["'][^>]*?content=["']([^"']+)["'][^>]*?>/i);
                                if (metaMatch && metaMatch[1]) {
                                    anchorText = metaMatch[1];
                                    // console.log('Found anchor text via meta tag for ' + city.slug + ': ' + anchorText);
                                } else {
                                    // Method 2: Look for data-anchor-text attribute
                                    var dataMatch = data.match(/data-anchor-text=["']([^"']+)["']/i);
                                    if (dataMatch && dataMatch[1]) {
                                        anchorText = dataMatch[1];
                                        // console.log('Found anchor text via data attribute for ' + city.slug + ': ' + anchorText);
                                    } else {
                                        // Method 3: Look for input[name='anchor_text']
                                        var inputMatch = data.match(/<input[^>]*?name=["']anchor_text["'][^>]*?value=["']([^"']+)["'][^>]*?>/i);
                                        if (inputMatch && inputMatch[1]) {
                                            anchorText = inputMatch[1];
                                            // console.log('Found anchor text via input for ' + city.slug + ': ' + anchorText);
                                        } else {
                                            // Method 4: Look for JSON text in script tags or serialized data
                                            var jsonMatch = data.match(/"anchor_text["']?\s*:\s*["']([^"']+)["']/i);
                                            if (jsonMatch && jsonMatch[1]) {
                                                anchorText = jsonMatch[1];
                                                // console.log('Found anchor text via JSON data for ' + city.slug + ': ' + anchorText);
                                            }
                                        }
                                    }
                                }

                                resolve({
                                    id: city.id,
                                    url: city.url,
                                    slug: city.slug,
                                    menuTitle: city.title,
                                    pageTitle: pageTitle.trim(),
                                    anchorText: anchorText
                                });
                            },
                            error: function () {
                                // If request fails, use menu title
                                resolve({
                                    id: city.id,
                                    url: city.url,
                                    slug: city.slug,
                                    menuTitle: city.title,
                                    pageTitle: city.title,
                                    anchorText: ''
                                });
                            }
                        });
                    }
                });
            });

            // When all page data is collected
            Promise.all(pagePromises).then(function (cityPagesWithTitles) {
                // Find common site name pattern in titles
                function findSiteNamePattern(titles) {
                    // Get all possible separators
                    var separators = [' - ', ' – ', ' | ', ' :: '];
                    var siteNamePattern = null;

                    // Try each separator
                    for (var i = 0; i < separators.length; i++) {
                        var separator = separators[i];
                        var potentialMatches = {};

                        // Check all titles for this separator
                        titles.forEach(function (title) {
                            var parts = title.split(separator);
                            if (parts.length > 1) {
                                // Get the last part as potential site name
                                var sitePart = parts[parts.length - 1].trim();
                                potentialMatches[sitePart] = potentialMatches[sitePart] || 0;
                                potentialMatches[sitePart]++;
                            }
                        });

                        // Find the most common site name pattern
                        var maxCount = 0;
                        var mostCommon = null;

                        for (var pattern in potentialMatches) {
                            if (potentialMatches[pattern] > maxCount) {
                                maxCount = potentialMatches[pattern];
                                mostCommon = pattern;
                            }
                        }

                        // If we found a pattern in more than 50% of titles, use it
                        if (mostCommon && maxCount >= titles.length * 0.5) {
                            siteNamePattern = {
                                separator: separator,
                                name: mostCommon
                            };
                            break;
                        }
                    }

                    return siteNamePattern;
                }

                // Get all titles
                var allTitles = cityPagesWithTitles.map(function (city) {
                    return city.pageTitle;
                });

                // Find site name pattern
                var sitePattern = findSiteNamePattern(allTitles);

                // Clean titles if pattern found
                if (sitePattern) {
                    cityPagesWithTitles.forEach(function (city) {
                        var fullPattern = sitePattern.separator + sitePattern.name;
                        if (city.pageTitle.endsWith(fullPattern)) {
                            city.pageTitle = city.pageTitle.substring(0, city.pageTitle.length - fullPattern.length).trim();
                        }
                    });
                }

                // Sort by title
                cityPagesWithTitles.sort(function (a, b) {
                    // Use anchor text for sorting if available
                    var aText = a.anchorText || a.pageTitle;
                    var bText = b.anchorText || b.pageTitle;
                    return aText.localeCompare(bText);
                });

                // Log the complete city data for debugging
                // console.log('Complete city pages data:', cityPagesWithTitles);

                $widgets.each(function () {
                    var $widget = $(this);

                    // Update widget title
                    var isElementor = $widget.closest(dynamicMenuData.related_elementor_selector).length > 0;
                    var $widgetTitle;

                    if (isElementor) {
                        $widgetTitle = $widget.closest(dynamicMenuData.related_elementor_selector).find('.related-locations-title');
                    } else {
                        // Try multiple ways to find the title
                        $widgetTitle = $widget.prev('h2.widget-title');
                        if (!$widgetTitle.length) {
                            $widgetTitle = $widget.find('.related-locations-title');
                        }
                        if (!$widgetTitle.length) {
                            $widgetTitle = $widget.closest('.widget').find('.widget-title');
                        }
                    }

                    if ($widgetTitle.length) {
                        $widgetTitle.text('Locations Served');

                        // Apply uppercase if setting is enabled
                        if (dynamicMenuData.uppercase_menu === 'yes') {
                            $widgetTitle.text($widgetTitle.text().toUpperCase());
                        }

                        // Make title visible now that it's properly set
                        $widgetTitle.css('visibility', 'visible');
                    }

                    // Update the list with anchor text if available
                    var $list = $widget.find('.related-locations-list');
                    $list.empty();

                    cityPagesWithTitles.forEach(function (city) {
                        // ALWAYS use anchor text if available, otherwise use page title
                        var displayText = city.anchorText || city.pageTitle;

                        // Add debug information to help diagnose issues
                        // console.log('City: ' + city.slug + ' | Using: ' + (city.anchorText ? 'ANCHOR TEXT: ' + city.anchorText : 'PAGE TITLE: ' + city.pageTitle));

                        $list.append(
                            '<li class="location-item">' +
                            '<a href="' + city.url + '" class="' + (city.anchorText ? 'using-anchor-text' : 'using-page-title') + '" ' +
                            'data-source="' + (city.anchorText ? 'anchor' : 'title') + '" ' +
                            'data-slug="' + city.slug + '" ' +
                            'data-anchor="' + (city.anchorText || '') + '" ' +
                            '>' +
                            displayText +
                            '</a>' +
                            '</li>'
                        );
                    });

                    // Handle empty case
                    if (cityPagesWithTitles.length === 0) {
                        $list.html('<li class="no-locations">No other locations available</li>');
                    }

                    // Add class to indicate content is loaded
                    $widget.addClass('content-loaded');
                });
            });
        } else {
            $widgets.each(function () {
                var $widget = $(this);
                var $list = $widget.find('.related-locations-list');
                $list.html('<li class="no-locations">No locations found</li>');

                // Make title visible
                var $widgetTitle = $widget.prev('h2.widget-title');
                if (!$widgetTitle.length) {
                    $widgetTitle = $widget.find('.related-locations-title');
                }
                if ($widgetTitle.length) {
                    $widgetTitle.css('visibility', 'visible');
                }

                // Add class to indicate content is loaded
                $widget.addClass('content-loaded');
            });
        }
    }

// Close the IIFE
})(jQuery);