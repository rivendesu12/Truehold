/**
 * Shared property filter state between listing (/properties) and map (/properties/map).
 * Uses sessionStorage + query strings on navigation.
 */
(function (global) {
    var STORAGE_KEY = 'truehold_property_filters_qs';
    // Marker the server (PropertyController::applyStickyFilters) reads as "this URL
    // states the filters — even if it states none". Without it a filter-less URL is
    // treated as bare navigation and the remembered filters are restored.
    var MARKER_KEY = 'qf';
    var FILTER_KEYS = [
        'location',
        'property_type',
        'min_price',
        'max_price',
        'available_date',
        'management_company',
        'couples_allowed',
        'ensuite',
        'agent_name',
        'paying_only',
        'room_count',
    ];

    function parseQueryString(qs) {
        if (!qs) return new URLSearchParams();
        return new URLSearchParams(qs.charAt(0) === '?' ? qs.slice(1) : qs);
    }

    function filterParamsOnly(params) {
        var out = new URLSearchParams();
        FILTER_KEYS.forEach(function (k) {
            if (params.has(k)) {
                var v = params.get(k);
                if (v !== null && v !== '') out.set(k, v);
            }
        });
        return out;
    }

    function hasFilterParamsInSearch(search) {
        var p = parseQueryString(search || window.location.search || '');
        return FILTER_KEYS.some(function (k) {
            return p.has(k) && p.get(k) !== '';
        });
    }

    // True when the URL is authoritative about the filter state, including the
    // deliberately-empty state (?qf=1), which must not be re-filled from storage.
    function expressesFilterState(search) {
        var p = parseQueryString(search || window.location.search || '');
        return p.has(MARKER_KEY) || hasFilterParamsInSearch(search);
    }

    function saveFromParams(params) {
        var p = filterParamsOnly(params);
        var s = p.toString();
        if (s) {
            try {
                sessionStorage.setItem(STORAGE_KEY, s);
            } catch (e) {}
        } else {
            try {
                sessionStorage.removeItem(STORAGE_KEY);
            } catch (e2) {}
        }
    }

    global.TrueholdPropertyFilters = {
        STORAGE_KEY: STORAGE_KEY,
        FILTER_KEYS: FILTER_KEYS,
        MARKER_KEY: MARKER_KEY,
        hasFilterParamsInSearch: hasFilterParamsInSearch,
        expressesFilterState: expressesFilterState,

        // Stamp a query string as authoritative so an empty one clears the
        // server-remembered filters instead of resurrecting them.
        markedQueryString: function (qs) {
            var p = parseQueryString(qs || '');
            p.set(MARKER_KEY, '1');
            return p.toString();
        },

        saveFromLocationSearch: function () {
            saveFromParams(parseQueryString(window.location.search || ''));
        },

        saveFromQueryString: function (qs) {
            saveFromParams(parseQueryString(qs || ''));
        },

        getStoredQueryString: function () {
            try {
                return sessionStorage.getItem(STORAGE_KEY) || '';
            } catch (e) {
                return '';
            }
        },

        clearStored: function () {
            try {
                sessionStorage.removeItem(STORAGE_KEY);
            } catch (e) {}
        },

        listingFormQueryString: function (form) {
            if (!form) return '';
            var fd = new FormData(form);
            var params = new URLSearchParams();
            fd.forEach(function (value, key) {
                if (value != null && value !== '') params.set(key, value);
            });
            return params.toString();
        },

        buildMapControlsQueryString: function () {
            var params = new URLSearchParams();
            var loc = document.getElementById('thf_location') || document.getElementById('filterLocation');
            if (loc && loc.value) params.set('location', loc.value);
            var pt = document.getElementById('thf_property_type') || document.getElementById('filterPropertyType');
            if (pt && pt.value) params.set('property_type', pt.value);
            var minP = document.getElementById('thf_min_price') || document.getElementById('filterMinPrice');
            if (minP && minP.value !== '') params.set('min_price', minP.value);
            var maxP = document.getElementById('thf_max_price') || document.getElementById('filterMaxPrice');
            if (maxP && maxP.value !== '') params.set('max_price', maxP.value);
            if (document.getElementById('filterCouplesYes') && document.getElementById('filterCouplesYes').checked) {
                params.set('couples_allowed', 'yes');
            } else if (document.getElementById('filterCouplesNo') && document.getElementById('filterCouplesNo').checked) {
                params.set('couples_allowed', 'no');
            }
            var ens = document.getElementById('filterEnsuite');
            if (ens && ens.checked) params.set('ensuite', 'yes');
            var rc = document.getElementById('thf_room_count') || document.getElementById('filterRoomCount');
            if (rc && rc.value) params.set('room_count', rc.value);
            var ag = document.getElementById('thf_agent_name') || document.getElementById('filterAgentName');
            if (ag && ag.value) params.set('agent_name', ag.value);
            var pay = document.getElementById('filterPayingOnly');
            if (pay && pay.checked) params.set('paying_only', '1');
            return params.toString();
        },

        applyQueryStringToListingForm: function (form, qs) {
            if (!form) return;
            var params = parseQueryString(qs || '');
            var setSelect = function (name) {
                if (!params.has(name)) return;
                var el = form.querySelector('[name="' + name + '"]');
                if (el && el.tagName === 'SELECT') {
                    el.value = params.get(name);
                }
            };
            var setInput = function (name) {
                if (!params.has(name)) return;
                var el = form.querySelector('[name="' + name + '"]');
                if (el && el.tagName === 'INPUT' && el.type === 'number') {
                    el.value = params.get(name);
                }
            };
            var locEl = form.querySelector('[name="location"]');
            if (locEl && params.has('location')) {
                locEl.value = params.get('location');
            } else {
                setSelect('location');
            }
            setSelect('property_type');
            setSelect('agent_name');
            setSelect('room_count');
            setInput('min_price');
            setInput('max_price');
            var payCb = form.querySelector('[name="paying_only"]');
            if (payCb && payCb.type === 'checkbox') {
                payCb.checked = params.get('paying_only') === '1' || params.get('paying_only') === 'true';
            }
            var coupleVal = params.has('couples_allowed') ? params.get('couples_allowed') : '';
            form.querySelectorAll('input[name="couples_allowed"]').forEach(function (r) {
                r.checked = r.value === coupleVal || (coupleVal === '' && r.value === '');
            });
            var ensCb = form.querySelector('[name="ensuite"][value="yes"]');
            if (ensCb && ensCb.type === 'checkbox') {
                ensCb.checked = params.get('ensuite') === 'yes';
            }
        },

        applyQueryStringToMapControls: function (qs) {
            var params = parseQueryString(qs || '');
            var loc = document.getElementById('thf_location') || document.getElementById('filterLocation');
            if (loc && params.has('location')) loc.value = params.get('location');
            var pt = document.getElementById('thf_property_type') || document.getElementById('filterPropertyType');
            if (pt && params.has('property_type')) pt.value = params.get('property_type');
            var minP = document.getElementById('thf_min_price') || document.getElementById('filterMinPrice');
            if (minP && params.has('min_price')) minP.value = params.get('min_price');
            var maxP = document.getElementById('thf_max_price') || document.getElementById('filterMaxPrice');
            if (maxP && params.has('max_price')) maxP.value = params.get('max_price');
            var cy = document.getElementById('filterCouplesYes');
            var cn = document.getElementById('filterCouplesNo');
            var ca = document.getElementById('filterCouplesAny');
            if (ca) ca.checked = true;
            if (cy) cy.checked = false;
            if (cn) cn.checked = false;
            if (params.get('couples_allowed') === 'yes' && cy) cy.checked = true;
            if (params.get('couples_allowed') === 'no' && cn) cn.checked = true;
            var ens = document.getElementById('filterEnsuite');
            if (ens) ens.checked = params.get('ensuite') === 'yes';
            var rc = document.getElementById('thf_room_count') || document.getElementById('filterRoomCount');
            if (rc && params.has('room_count')) rc.value = params.get('room_count');
            var ag = document.getElementById('thf_agent_name') || document.getElementById('filterAgentName');
            if (ag && params.has('agent_name')) ag.value = params.get('agent_name');
            var pay = document.getElementById('filterPayingOnly');
            if (pay) pay.checked = params.get('paying_only') === '1' || params.get('paying_only') === 'true';
        },
    };
})(typeof window !== 'undefined' ? window : globalThis);
