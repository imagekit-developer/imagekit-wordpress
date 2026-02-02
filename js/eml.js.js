/******/ (function() { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/js/eml.js":
/*!***********************!*\
  !*** ./src/js/eml.js ***!
  \***********************/
/***/ (function(__unused_webpack_module, exports) {



exports.InitialViewParameterEnum = void 0;
(function (InitialViewParameterEnum) {
  InitialViewParameterEnum["SEARCH_QUERY"] = "searchQuery";
  InitialViewParameterEnum["FOLDER_PATH"] = "folderPath";
  InitialViewParameterEnum["FILE_ID"] = "fileId";
  InitialViewParameterEnum["COLLECTION"] = "collection";
  InitialViewParameterEnum["FILE_TYPE"] = "fileType";
})(exports.InitialViewParameterEnum || (exports.InitialViewParameterEnum = {}));
exports.FileTypeValue = void 0;
(function (FileTypeValue) {
  FileTypeValue["IMAGE"] = "images";
  FileTypeValue["VIDEO"] = "videos";
  FileTypeValue["CSSJS"] = "cssJs";
  FileTypeValue["OTHERS"] = "others";
})(exports.FileTypeValue || (exports.FileTypeValue = {}));
var ImagekitMediaLibraryWidget = /** @class */function () {
  function ImagekitMediaLibraryWidget(options, callback) {
    var _this = this;
    this.IK_HOST = 'https://eml.imagekit.io';
    this.IK_FRAME_TITLE = 'ImageKit Embedded Media Library';
    // Create global element references
    this.widgetHost = window.location.href;
    // Define option defaults 
    this.options = this.getDefaultOptions();
    // Create options by extending defaults with the passed in arguments
    if (options && typeof options === 'object') {
      Object.assign(this.options, options);
    }
    // Set callback function
    this.callbackFunction = callback && typeof callback === "function" ? callback : function () {};
    this.view = this.options.view;
    // Initialize event handlers for later removal
    this.windowClickHandler = function (event) {
      if (_this.modal && event.target === _this.modal) {
        _this.close();
      }
    };
    this.messageHandler = function (event) {
      var _a;
      if (event.origin !== _this.IK_HOST) {
        return;
      }
      if (event.source !== ((_a = _this.iframe) === null || _a === void 0 ? void 0 : _a.contentWindow)) return;
      if (event.data.eventType === "CLOSE_MEDIA_LIBRARY_WIDGET" || event.data.eventType === "INSERT") {
        _this.callbackFunction(event.data);
        _this.close();
      }
    };
    this.registerStyles();
    this.buildOut();
    this.setListeners();
  }
  ImagekitMediaLibraryWidget.prototype.getDefaultOptions = function () {
    return {
      className: "",
      container: "",
      containerDimensions: {
        height: '100%',
        width: '100%'
      },
      dimensions: {
        height: '100%',
        width: '100%'
      },
      style: {
        border: 'none'
      },
      view: 'modal',
      renderOpenButton: true
    };
  };
  ImagekitMediaLibraryWidget.prototype.registerStyles = function () {
    this.styleEl = document.createElement('style');
    this.styleEl.innerHTML = "\n            /* The Modal (background) */\n            .ik-media-library-widget-modal {\n                display: none; /* Hidden by default */\n                position: fixed; /* Stay in place */\n                z-index: 1; /* Sit on top */\n                padding-top: 2%; /* Location of the box */\n                left: 0;\n                top: 0;\n                width: 100%; /* Full width */\n                height: 100%; /* Full height */\n                overflow: auto; /* Enable scroll if needed */\n                background-color: rgb(0,0,0); /* Fallback color */\n                background-color: rgba(0,0,0,0.4); /* Black w/ opacity */\n            }\n            \n            /* Modal Content */\n            .ik-media-library-widget-modal-content {\n                background-color: #fefefe;\n                margin: auto;\n                border: 1px solid #888;\n                width: 96%;\n                height: 94%;\n                position: relative;\n            }\n\n            /* Loading overlay */\n            .ik-media-library-widget-loading-overlay {\n                position: absolute;\n                top: 0;\n                left: 0;\n                width: 100%;\n                height: 100%;\n                background-color: rgba(255, 255, 255, 0.9);\n                display: flex;\n                align-items: center;\n                justify-content: center;\n                z-index: 10;\n            }\n\n            .ik-media-library-widget-loading-spinner {\n                border: 4px solid #f3f3f3;\n                border-top: 4px solid #3498db;\n                border-radius: 50%;\n                width: 40px;\n                height: 40px;\n                animation: ik-media-library-widget-spin 1s linear infinite;\n            }\n\n            @keyframes ik-media-library-widget-spin {\n                0% { transform: rotate(0deg); }\n                100% { transform: rotate(360deg); }\n            }\n\n            .ik-media-library-widget-loading-overlay.hidden {\n                display: none;\n            }\n        ";
    document.head.appendChild(this.styleEl);
  };
  ImagekitMediaLibraryWidget.prototype.buildOut = function () {
    var _this = this;
    var _a, _b, _c, _d, _e, _f, _g, _h, _j;
    var container, docFragment, mainFrame, button;
    // If container is an HTML string, find the DOM node, else use it directly.
    if (typeof this.options.container === "string") {
      container = document.querySelector(this.options.container);
    } else {
      container = this.options.container;
    }
    // Create a DocumentFragment to build with
    docFragment = document.createDocumentFragment();
    // Create ikFrame element
    this.ikFrame = document.createElement("div");
    this.ikFrame.className = this.options.className || ""; // Assign an empty string as the default value
    this.ikFrame.style.height = ((_b = (_a = this.options) === null || _a === void 0 ? void 0 : _a.containerDimensions) === null || _b === void 0 ? void 0 : _b.height) || "100%";
    this.ikFrame.style.width = ((_d = (_c = this.options) === null || _c === void 0 ? void 0 : _c.containerDimensions) === null || _d === void 0 ? void 0 : _d.width) || "100%";
    mainFrame = document.createElement("iframe");
    mainFrame.title = this.IK_FRAME_TITLE;
    mainFrame.src = this.generateInitialUrl();
    mainFrame.setAttribute('sandbox', 'allow-top-navigation allow-same-origin allow-scripts allow-forms allow-modals allow-popups allow-popups-to-escape-sandbox allow-downloads');
    mainFrame.setAttribute('allow', 'clipboard-write');
    mainFrame.height = ((_f = (_e = this.options) === null || _e === void 0 ? void 0 : _e.dimensions) === null || _f === void 0 ? void 0 : _f.height) || "100%";
    mainFrame.width = ((_h = (_g = this.options) === null || _g === void 0 ? void 0 : _g.dimensions) === null || _h === void 0 ? void 0 : _h.width) || "100%";
    mainFrame.style.border = this.options.style.border;
    this.iframe = mainFrame;
    this.ikFrame.appendChild(mainFrame);
    if (((_j = this.view) === null || _j === void 0 ? void 0 : _j.toLowerCase()) !== 'modal') {
      // Add relative positioning for loading overlay
      this.ikFrame.style.position = "relative";
      // create loading overlay for inline view
      var loadingOverlay = document.createElement("div");
      loadingOverlay.classList.add("ik-media-library-widget-loading-overlay", "hidden");
      var spinner = document.createElement("div");
      spinner.classList.add("ik-media-library-widget-loading-spinner");
      loadingOverlay.appendChild(spinner);
      this.loadingOverlay = loadingOverlay;
      this.ikFrame.appendChild(loadingOverlay);
      // Append ikFrame to DocumentFragment
      docFragment.appendChild(this.ikFrame);
      // Append DocumentFragment to body
      if (container) container.appendChild(docFragment);
    } else {
      if (this.options.renderOpenButton) {
        // create button
        button = document.createElement("button");
        button.innerHTML = "Open Media Library";
        button.onclick = function () {
          if (_this.modal) {
            _this.modal.style.display = "block";
          }
        };
      }
      // create modal
      var modal = document.createElement("div");
      var modalContent = document.createElement("div");
      modal.classList.add("ik-media-library-widget-modal");
      modalContent.classList.add("ik-media-library-widget-modal-content");
      // create loading overlay
      var loadingOverlay = document.createElement("div");
      loadingOverlay.classList.add("ik-media-library-widget-loading-overlay", "hidden");
      var spinner = document.createElement("div");
      spinner.classList.add("ik-media-library-widget-loading-spinner");
      loadingOverlay.appendChild(spinner);
      this.loadingOverlay = loadingOverlay;
      modalContent.appendChild(loadingOverlay);
      modalContent.appendChild(this.ikFrame);
      modal.appendChild(modalContent);
      this.modal = modal;
      // append button and modal to docFragment
      if (button && this.options.renderOpenButton) {
        docFragment.appendChild(button);
      }
      docFragment.appendChild(modal);
      // append docFragment to container
      if (container) container.appendChild(docFragment);
    }
    if (this.iframe) {
      this.setLoading(true);
      this.setupIframeLoadHandler();
    }
  };
  ImagekitMediaLibraryWidget.prototype.generateInitialUrl = function () {
    var _a, _b, _c, _d, _e, _f, _g, _h;
    var baseUrl = "".concat(this.IK_HOST, "/media-library-widget");
    var params = new URLSearchParams({
      redirectTo: 'media-library-widget',
      isMediaLibraryWidget: 'true',
      widgetHost: this.widgetHost
    });
    // Add initial view parameters if they exist
    if ((_b = (_a = this.options) === null || _a === void 0 ? void 0 : _a.mlSettings) === null || _b === void 0 ? void 0 : _b.initialView) {
      var key = Object.keys(this.options.mlSettings.initialView)[0];
      if (Object.values(exports.InitialViewParameterEnum).includes(key)) {
        params.append('mlWidgetInitialView', btoa(JSON.stringify(this.options.mlSettings.initialView)));
      }
    }
    // Add custom query parameters if they exist
    if ((_d = (_c = this.options) === null || _c === void 0 ? void 0 : _c.mlSettings) === null || _d === void 0 ? void 0 : _d.queryParams) {
      Object.entries(this.options.mlSettings.queryParams).forEach(function (_a) {
        var key = _a[0],
          value = _a[1];
        params.append(key, String(value));
      });
    }
    // Add loginViaSSO if it exists
    if ((_f = (_e = this.options) === null || _e === void 0 ? void 0 : _e.mlSettings) === null || _f === void 0 ? void 0 : _f.loginViaSSO) {
      params.append('loginViaSSO', 'true');
    }
    // Add widgetImagekitId if it exists
    if ((_h = (_g = this.options) === null || _g === void 0 ? void 0 : _g.mlSettings) === null || _h === void 0 ? void 0 : _h.widgetImagekitId) {
      params.append('widgetImagekitId', this.options.mlSettings.widgetImagekitId);
    }
    return "".concat(baseUrl, "?").concat(params.toString());
  };
  ImagekitMediaLibraryWidget.prototype.setLoading = function (isLoading) {
    if (!this.loadingOverlay) return;
    if (isLoading) {
      this.loadingOverlay.classList.remove("hidden");
      if (this.iframe) {
        this.iframe.style.visibility = "hidden";
      }
    } else {
      this.loadingOverlay.classList.add("hidden");
      if (this.iframe) {
        this.iframe.style.visibility = "visible";
      }
    }
  };
  ImagekitMediaLibraryWidget.prototype.setupIframeLoadHandler = function () {
    var _this = this;
    if (this.iframe) {
      this.iframe.onload = function () {
        if (_this.iframe && _this.iframe.contentWindow) {
          _this.iframe.contentWindow.postMessage(JSON.stringify({
            mlSettings: _this.options.mlSettings
          }), _this.IK_HOST);
        }
        _this.setLoading(false);
      };
    }
  };
  ImagekitMediaLibraryWidget.prototype.open = function (settings, callback) {
    var _a;
    if (callback && typeof callback === "function") {
      this.callbackFunction = callback;
    }
    if (settings) {
      this.options.mlSettings = structuredClone(settings.mlSettings || {});
      if (this.iframe) {
        this.setLoading(true);
        this.iframe.src = this.generateInitialUrl();
        this.setupIframeLoadHandler();
      }
    }
    if (((_a = this.view) === null || _a === void 0 ? void 0 : _a.toLowerCase()) === 'modal' && this.modal) {
      this.modal.style.display = "block";
    }
  };
  ImagekitMediaLibraryWidget.prototype.close = function () {
    var _a;
    if (((_a = this.view) === null || _a === void 0 ? void 0 : _a.toLowerCase()) === 'modal') {
      this.closeModal();
    }
  };
  ImagekitMediaLibraryWidget.prototype.closeModal = function () {
    if (this.modal) {
      this.modal.style.display = "none";
    }
  };
  ImagekitMediaLibraryWidget.prototype.destroy = function () {
    window.removeEventListener("click", this.windowClickHandler);
    window.removeEventListener("message", this.messageHandler);
    if (this.modal) {
      this.modal.remove();
      this.modal = undefined;
    } else if (this.ikFrame && this.ikFrame.parentNode) {
      this.ikFrame.parentNode.removeChild(this.ikFrame);
    }
    if (this.styleEl) {
      this.styleEl.remove();
      this.styleEl = undefined;
    }
    // Clear references
    this.iframe = undefined;
    this.loadingOverlay = undefined;
  };
  ImagekitMediaLibraryWidget.prototype.setListeners = function () {
    window.addEventListener("click", this.windowClickHandler);
    window.addEventListener("message", this.messageHandler);
  };
  return ImagekitMediaLibraryWidget;
}();
window.IKMediaLibraryWidget = ImagekitMediaLibraryWidget;
exports.ImagekitMediaLibraryWidget = ImagekitMediaLibraryWidget;

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module is referenced by other modules so it can't be inlined
/******/ 	var __webpack_exports__ = {};
/******/ 	__webpack_modules__["./src/js/eml.js"](0,__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=eml.js.js.map