/******/ (function() { // webpackBootstrap
/*!***********************!*\
  !*** ./src/js/eml.js ***!
  \***********************/
const InitialViewParameterEnum = {
  "SEARCH_QUERY": "searchQuery",
  "FOLDER_PATH": "folderPath",
  "FILE_ID": "fileId",
  "COLLECTION": "collection",
  "FILE_TYPE": "fileType"
};
const FileTypeValue = {
  "IMAGE": "images",
  "VIDEO": "videos",
  "CSSJS": "cssJs",
  "OTHERS": "others"
};
class ImagekitMediaLibraryWidget {
  getDefaultOptions() {
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
  }
  constructor(options, callback) {
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
    this.callbackFunction = callback && typeof callback === "function" ? callback : () => {};
    this.view = this.options.view;
    // Initialize event handlers for later removal
    this.windowClickHandler = event => {
      if (this.modal && event.target === this.modal) {
        this.close();
      }
    };
    this.messageHandler = event => {
      var _a;
      if (event.origin !== this.IK_HOST) {
        return;
      }
      if (event.source !== ((_a = this.iframe) === null || _a === void 0 ? void 0 : _a.contentWindow)) return;
      if (event.data.eventType === "CLOSE_MEDIA_LIBRARY_WIDGET" || event.data.eventType === "INSERT") {
        this.callbackFunction(event.data);
        this.close();
      }
    };
    this.registerStyles();
    this.buildOut();
    this.setListeners();
  }
  registerStyles() {
    this.styleEl = document.createElement('style');
    this.styleEl.innerHTML = `
            /* The Modal (background) */
            .ik-media-library-widget-modal {
                display: none; /* Hidden by default */
                position: fixed; /* Stay in place */
                z-index: 1; /* Sit on top */
                padding-top: 2%; /* Location of the box */
                left: 0;
                top: 0;
                width: 100%; /* Full width */
                height: 100%; /* Full height */
                overflow: auto; /* Enable scroll if needed */
                background-color: rgb(0,0,0); /* Fallback color */
                background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            }
            
            /* Modal Content */
            .ik-media-library-widget-modal-content {
                background-color: #fefefe;
                margin: auto;
                border: 1px solid #888;
                width: 96%;
                height: 94%;
                position: relative;
            }

            /* Loading overlay */
            .ik-media-library-widget-loading-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(255, 255, 255, 0.9);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10;
            }

            .ik-media-library-widget-loading-spinner {
                border: 4px solid #f3f3f3;
                border-top: 4px solid #3498db;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: ik-media-library-widget-spin 1s linear infinite;
            }

            @keyframes ik-media-library-widget-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            .ik-media-library-widget-loading-overlay.hidden {
                display: none;
            }
        `;
    document.head.appendChild(this.styleEl);
  }
  buildOut() {
    var _a, _b, _c, _d, _e, _f, _g, _h, _j;
    let container, docFragment, mainFrame, button;
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
      const loadingOverlay = document.createElement("div");
      loadingOverlay.classList.add("ik-media-library-widget-loading-overlay", "hidden");
      const spinner = document.createElement("div");
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
        button.onclick = () => {
          if (this.modal) {
            this.modal.style.display = "block";
          }
        };
      }
      // create modal
      const modal = document.createElement("div");
      const modalContent = document.createElement("div");
      modal.classList.add("ik-media-library-widget-modal");
      modalContent.classList.add("ik-media-library-widget-modal-content");
      // create loading overlay
      const loadingOverlay = document.createElement("div");
      loadingOverlay.classList.add("ik-media-library-widget-loading-overlay", "hidden");
      const spinner = document.createElement("div");
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
  }
  generateInitialUrl() {
    var _a, _b, _c, _d, _e, _f, _g, _h;
    const baseUrl = `${this.IK_HOST}/media-library-widget`;
    const params = new URLSearchParams({
      redirectTo: 'media-library-widget',
      isMediaLibraryWidget: 'true',
      widgetHost: this.widgetHost
    });
    // Add initial view parameters if they exist
    if ((_b = (_a = this.options) === null || _a === void 0 ? void 0 : _a.mlSettings) === null || _b === void 0 ? void 0 : _b.initialView) {
      const key = Object.keys(this.options.mlSettings.initialView)[0];
      if (Object.values(InitialViewParameterEnum).includes(key)) {
        params.append('mlWidgetInitialView', btoa(JSON.stringify(this.options.mlSettings.initialView)));
      }
    }
    // Add custom query parameters if they exist
    if ((_d = (_c = this.options) === null || _c === void 0 ? void 0 : _c.mlSettings) === null || _d === void 0 ? void 0 : _d.queryParams) {
      Object.entries(this.options.mlSettings.queryParams).forEach(([key, value]) => {
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
    return `${baseUrl}?${params.toString()}`;
  }
  setLoading(isLoading) {
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
  }
  setupIframeLoadHandler() {
    if (this.iframe) {
      this.iframe.onload = () => {
        if (this.iframe && this.iframe.contentWindow) {
          this.iframe.contentWindow.postMessage(JSON.stringify({
            mlSettings: this.options.mlSettings
          }), this.IK_HOST);
        }
        this.setLoading(false);
      };
    }
  }
  open(settings, callback) {
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
  }
  close() {
    var _a;
    if (((_a = this.view) === null || _a === void 0 ? void 0 : _a.toLowerCase()) === 'modal') {
      this.closeModal();
    }
  }
  closeModal() {
    if (this.modal) {
      this.modal.style.display = "none";
    }
  }
  destroy() {
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
  }
  setListeners() {
    window.addEventListener("click", this.windowClickHandler);
    window.addEventListener("message", this.messageHandler);
  }
}
window.IKMediaLibraryWidget = ImagekitMediaLibraryWidget;
/******/ })()
;
//# sourceMappingURL=eml.js.map