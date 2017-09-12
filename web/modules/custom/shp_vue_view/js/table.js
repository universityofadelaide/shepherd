webpackJsonp([0],{

/***/ 10:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0_vue__ = __webpack_require__(3);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_axios__ = __webpack_require__(4);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_1_axios___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_1_axios__);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2__Table_vue__ = __webpack_require__(30);
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_2__Table_vue___default = __webpack_require__.n(__WEBPACK_IMPORTED_MODULE_2__Table_vue__);


// This is a vue js component.


// Make available to all child components.
window.Axios = __WEBPACK_IMPORTED_MODULE_1_axios___default.a;

var vue_table = new __WEBPACK_IMPORTED_MODULE_0_vue__["default"]({
  el: '#vue_table__app',
  render: function render(h) {
    return h(__WEBPACK_IMPORTED_MODULE_2__Table_vue___default.a);
  }
});

/***/ }),

/***/ 30:
/***/ (function(module, exports, __webpack_require__) {

var disposed = false
var Component = __webpack_require__(31)(
  /* script */
  __webpack_require__(32),
  /* template */
  __webpack_require__(33),
  /* styles */
  null,
  /* scopeId */
  null,
  /* moduleIdentifier (server only) */
  null
)
Component.options.__file = "/Users/chris/code/shepherd/web/modules/custom/shp_vue_view/js/src/table/Table.vue"
if (Component.esModule && Object.keys(Component.esModule).some(function (key) {return key !== "default" && key.substr(0, 2) !== "__"})) {console.error("named exports are not supported in *.vue files.")}
if (Component.options.functional) {console.error("[vue-loader] Table.vue: functional components are not supported with templates, they should use render functions.")}

/* hot reload */
if (false) {(function () {
  var hotAPI = require("vue-hot-reload-api")
  hotAPI.install(require("vue"), false)
  if (!hotAPI.compatible) return
  module.hot.accept()
  if (!module.hot.data) {
    hotAPI.createRecord("data-v-2d1c8e8b", Component.options)
  } else {
    hotAPI.reload("data-v-2d1c8e8b", Component.options)
  }
  module.hot.dispose(function (data) {
    disposed = true
  })
})()}

module.exports = Component.exports


/***/ }),

/***/ 31:
/***/ (function(module, exports) {

/* globals __VUE_SSR_CONTEXT__ */

// this module is a runtime utility for cleaner component module output and will
// be included in the final webpack user bundle

module.exports = function normalizeComponent (
  rawScriptExports,
  compiledTemplate,
  injectStyles,
  scopeId,
  moduleIdentifier /* server only */
) {
  var esModule
  var scriptExports = rawScriptExports = rawScriptExports || {}

  // ES6 modules interop
  var type = typeof rawScriptExports.default
  if (type === 'object' || type === 'function') {
    esModule = rawScriptExports
    scriptExports = rawScriptExports.default
  }

  // Vue.extend constructor export interop
  var options = typeof scriptExports === 'function'
    ? scriptExports.options
    : scriptExports

  // render functions
  if (compiledTemplate) {
    options.render = compiledTemplate.render
    options.staticRenderFns = compiledTemplate.staticRenderFns
  }

  // scopedId
  if (scopeId) {
    options._scopeId = scopeId
  }

  var hook
  if (moduleIdentifier) { // server build
    hook = function (context) {
      // 2.3 injection
      context =
        context || // cached call
        (this.$vnode && this.$vnode.ssrContext) || // stateful
        (this.parent && this.parent.$vnode && this.parent.$vnode.ssrContext) // functional
      // 2.2 with runInNewContext: true
      if (!context && typeof __VUE_SSR_CONTEXT__ !== 'undefined') {
        context = __VUE_SSR_CONTEXT__
      }
      // inject component styles
      if (injectStyles) {
        injectStyles.call(this, context)
      }
      // register component module identifier for async chunk inferrence
      if (context && context._registeredComponents) {
        context._registeredComponents.add(moduleIdentifier)
      }
    }
    // used by ssr in case component is cached and beforeCreate
    // never gets called
    options._ssrRegister = hook
  } else if (injectStyles) {
    hook = injectStyles
  }

  if (hook) {
    var functional = options.functional
    var existing = functional
      ? options.render
      : options.beforeCreate
    if (!functional) {
      // inject component registration as beforeCreate hook
      options.beforeCreate = existing
        ? [].concat(existing, hook)
        : [hook]
    } else {
      // register for functioal component in vue file
      options.render = function renderWithStyleInjection (h, context) {
        hook.call(context)
        return existing(h, context)
      }
    }
  }

  return {
    esModule: esModule,
    exports: scriptExports,
    options: options
  }
}


/***/ }),

/***/ 32:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
//
//
//
//
//
//
//
//
//
//
//
//
//
//
//

// We can assume that the drupalSettings are here.
// 10 second response timeout.
var AJAX_REQUEST_TIMEOUT = 10000;
var UNCHANGED_STATE_DISCONNECT = 15;
var FAILED_REQUEST_DISCONNECT = 3;
var POLL_UPDATE_CYCLE = drupalSettings.vue_table.update_cycle;
var POLL_RECONNECT_TIMEOUT = 900000;

var poller = void 0;
var pollTiming = POLL_UPDATE_CYCLE;
var reconnectPollerTimer = null;
var field_options = drupalSettings.vue_table.field_options;
var field_apis = [];
var previous_state = [];
// Store the number of unchanged calls.
// Once this reaches the number defined, we hang up on the ajax calls.
var disconnect_count = 0;

Object.keys(field_options).forEach(function (value, key) {
  if (field_options[value].active && field_options[value].url_endpoint.length > 0) {
    field_apis.push({
      name: value,
      url: field_options[value].url_endpoint,
      prop: field_options[value].javascript_property
    });
  }
});

/**
 * Recurse downwards for specified property in an Array|Object.
 *
 * @param {Object} data - The object
 * @param {string} prop - The property to search for.
 * @returns {string} - The string
 */
function findProp(data, prop) {
  return data.map(function (v) {
    // if v is an array or something.
    if (typeof v[prop] !== "undefined") {
      return v[prop];
    }
  });
}

/**
 * Parses the returned data from the ajax requests.
 *
 * @param {Object} results - Object containing results from request.
 */
function parseResults(results) {
  return results.map(function (v, i) {
    if (typeof v.data === "string") {
      return v.data[field_apis[i].prop];
    }
    // Assume we have and object to deal with
    // use a utility method to recursively find the value we want.
    return findProp(v.data, field_apis[i].prop);
  });
}

/* harmony default export */ __webpack_exports__["default"] = ({
  data: function data() {
    return {
      fields: drupalSettings.vue_table.fields,
      view: drupalSettings.vue_table.view,
      update_cycle: ''
    };
  },
  mounted: function mounted() {
    var _this = this;

    // Setup the polling
    if (Object.keys(field_apis).length > 0) {
      poller = setInterval(function () {
        _this.poll();
      }, pollTiming);
    } else {
      console.warn('No fields associated with an api, no polling started');
    }
  },

  methods: {
    /**
     * Performs ajax request(s) for all field(s).
     */
    poll: function poll() {
      var _this2 = this;

      // We have X number of urls to hit. Map over and get an array of promise objects.
      var requests = field_apis.map(function (v, i) {
        return Axios.get(field_apis[i].url, {
          // Set the timeout.
          timeout: AJAX_REQUEST_TIMEOUT
        });
      });
      // @todo - set the show property to show a spinner
      // but only if the current value is unknown.
      // When they are all done, we can unpack them.
      Axios.all(requests).then(function (results) {
        var data = parseResults(results);
        // Call update, which will handle updating the model.
        _this2.update(data);
      })
      /**
       * Catch errors with the multiple concurrent requests.
       */
      .catch(function (error) {
        /** @type {string} - Message to send to dialog */
        var msg = void 0;
        if (error.response) {
          msg = "Error :\n            The server responed with :  " + error.response.status + "\n            Message : " + error.response.data + "\n            ";
        } else if (error.request) {
          msg = "Error : " + error.request;
        } else {
          msg = "Error : " + error.message;
        }
        // Log out error.
        console.error(msg);
        disconnect_count++;
        // Terminate the connect if we reach a limit.
        if (disconnect_count >= FAILED_REQUEST_DISCONNECT) {
          _this2.disconnectPollingTimer('Maximum failed requests reached.');
          _this2.reconnectPollingTimer();
        }
      });
    },

    /**
     * Updates the view
     *
     * @param {Object} data - The current state.
     */
    update: function update(data) {
      this.checkPreviousState(data);
      var context = this;
      // For each of the fields with endpoints
      field_apis.forEach(function (value, index) {
        var field_name = value.name;
        var field_index = index;
        // Each view > row apply the updates.
        context.view.map(function (row, key) {
          row[field_name] = data[field_index][key];
        });
      });
    },

    /**
     * Checks the previous state against the new.
     * If previous state is equal to current state, increment the disconnect counter and throttle the timer.
     * Once limit is reached, disconnect polling for 15 minutes.
     *
     * @param {Object} data - Current state.
     */
    checkPreviousState: function checkPreviousState(data) {
      // Use underscores comparison method for objects/multidimensional arrays.
      if (_.isEqual(previous_state, data)) {
        // State matches previous.
        // Time to back up the timeout.
        this.throttlePoll();
        disconnect_count++;
        if (disconnect_count >= UNCHANGED_STATE_DISCONNECT) {
          this.disconnectPollingTimer('No new state changes detected.');
          this.reconnectPollingTimer();
        }
      }
      previous_state = data;
    },

    /**
     * Doubles the timer for polling.
     */
    throttlePoll: function throttlePoll() {
      var _this3 = this;

      // clear out the existing interval and reset with a new one.
      pollTiming = pollTiming * 2; // Double out the time.
      clearInterval(poller);
      poller = setInterval(function () {
        _this3.poll();
      }, pollTiming);
    },

    /**
     * Disconnect the polling interval.
     *
     * @param {string} msg - Log message giving reason for disconnection.
     */
    disconnectPollingTimer: function disconnectPollingTimer(msg) {
      clearInterval(poller);
      poller = null;
      console.info('Disconnecting polling : ' + msg);
    },

    /**
     * Reconnect polling timer.
     */
    reconnectPollingTimer: function reconnectPollingTimer() {
      var context = this;
      // Clear any previous timer id.
      if (reconnectPollerTimer) {
        clearTimeout(reconnectPollerTimer);
        reconnectPollerTimer = null;
      }
      reconnectPollerTimer = setTimeout(function () {
        console.info('Restarting polling');
        pollTiming = POLL_UPDATE_CYCLE;
        disconnect_count = 0;
        poller = setInterval(function () {
          context.poll();
        }, pollTiming);
      }, POLL_RECONNECT_TIMEOUT);
    }
  }
});

/***/ }),

/***/ 33:
/***/ (function(module, exports, __webpack_require__) {

module.exports={render:function (){var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;
  return _c('div', [_c('table', [_c('thead', _vm._l((_vm.fields), function(field) {
    return _c('th', [_vm._v(_vm._s(field.label))])
  })), _vm._v(" "), _c('tbody', _vm._l((_vm.view), function(row) {
    return _c('tr', _vm._l((row), function(col) {
      return _c('td', {
        domProps: {
          "innerHTML": _vm._s(col)
        }
      })
    }))
  }))])])
},staticRenderFns: []}
module.exports.render._withStripped = true
if (false) {
  module.hot.accept()
  if (module.hot.data) {
     require("vue-hot-reload-api").rerender("data-v-2d1c8e8b", module.exports)
  }
}

/***/ })

},[10]);