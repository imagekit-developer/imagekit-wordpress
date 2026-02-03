import apiFetch from "@wordpress/api-fetch";

class Wizard {
  constructor(context) {
    this.context = context;
    this.stepIndicator = context.querySelector(".ik-wizard-step");

    this.tabsWrap = context.querySelector(".ik-wizard-tabs");
    this.tabs = [...context.querySelectorAll(".ik-wizard-tab[data-step]")];
    this.panels = [...context.querySelectorAll(".ik-wizard-panel[data-step]")];

    this.buttonBack = context.querySelector('[data-wizard-action="back"]');
    this.buttonNext = context.querySelector('[data-wizard-action="next"]');
    this.buttonFinish = context.querySelector('[data-wizard-action="finish"]');

    this.maxStep = this.panels.length || 1;
    this.visibleMaxStep = Math.max(1, this.maxStep - 1);
    this.currentStep = this._getInitialStep();
    this.isSaving = false;
    this.isTesting = false;
    this.nonceMiddlewareSet = false;
    this.step2ErrorEl = context.querySelector('[data-wizard-error="step2"]');
    this.step2DefaultErrorMessage = this.step2ErrorEl
      ? this.step2ErrorEl.textContent
      : "";
    this.step2DebounceTimer = null;
    this.step2DebounceMs = 600;
    this.step2ValidatedPayloadKey = null;
    this.step2FieldErrorEls = {
      urlEndpoint: context.querySelector(
        '[data-wizard-field-error="urlEndpoint"]',
      ),
      publicKey: context.querySelector('[data-wizard-field-error="publicKey"]'),
      privateKey: context.querySelector(
        '[data-wizard-field-error="privateKey"]',
      ),
    };

    this._bind();
    this._prefillFromData();
    this.goTo(this.currentStep, { focusPanel: false });
  }

  _prefillFromData() {
    const data =
      window.ikData && window.ikData.wizard ? window.ikData.wizard : null;
    const prefill = data && data.prefill ? data.prefill : null;
    if (!prefill || typeof prefill !== "object") {
      return;
    }

    const urlInput = this.context.querySelector("#ik_wizard_url_endpoint");
    const publicKeyInput = this.context.querySelector("#ik_wizard_public_key");
    const privateKeyInput = this.context.querySelector(
      "#ik_wizard_private_key",
    );

    const setIfEmpty = (input, value) => {
      if (!input) {
        return;
      }
      if (input.value && input.value.trim().length > 0) {
        return;
      }
      if (typeof value !== "string" || value.trim().length === 0) {
        return;
      }
      input.value = value;
      input.dispatchEvent(new Event("input", { bubbles: true }));
    };

    setIfEmpty(urlInput, prefill.urlEndpoint);
    setIfEmpty(publicKeyInput, prefill.publicKey);
    setIfEmpty(privateKeyInput, prefill.privateKey);
  }

  _getInitialStep() {
    const activePanel = this.panels.find((panel) =>
      panel.classList.contains("is-active"),
    );
    if (activePanel && activePanel.dataset.step) {
      return this._clampStep(parseInt(activePanel.dataset.step, 10));
    }

    const activeTab = this.tabs.find((tab) =>
      tab.classList.contains("is-active"),
    );
    if (activeTab && activeTab.dataset.step) {
      return this._clampStep(parseInt(activeTab.dataset.step, 10));
    }

    return 1;
  }

  _clampStep(step) {
    if (!Number.isFinite(step)) {
      return 1;
    }
    return Math.min(Math.max(step, 1), this.maxStep);
  }

  _bind() {
    // Tab pills are visual-only; navigation happens via Back/Next.

    const step2UrlInput = this.context.querySelector("#ik_wizard_url_endpoint");
    const step2PublicKeyInput = this.context.querySelector(
      "#ik_wizard_public_key",
    );
    const step2PrivateKeyInput = this.context.querySelector(
      "#ik_wizard_private_key",
    );
    [step2UrlInput, step2PublicKeyInput, step2PrivateKeyInput]
      .filter(Boolean)
      .forEach((input) => {
        input.addEventListener("input", () => {
          this._onStep2InputChange();
        });
      });

    if (this.buttonBack) {
      this.buttonBack.addEventListener("click", (ev) => {
        ev.preventDefault();
        this.goTo(this.currentStep - 1);
      });
    }

    if (this.buttonNext) {
      this.buttonNext.addEventListener("click", async (ev) => {
        ev.preventDefault();

        if (this.currentStep === 2 && !this._validateStep2()) {
          return;
        }

        if (this.currentStep === 2) {
          const payload = this._getWizardPayload();
          const key = this._getWizardPayloadKey(payload);
          if (
            this.step2ValidatedPayloadKey &&
            this.step2ValidatedPayloadKey === key
          ) {
            this.goTo(3);
            return;
          }
          await this._testConnectionAndContinue();
          return;
        }

        if (this.currentStep === 3 && this.maxStep >= 4) {
          await this._saveWizardAndContinue();
          return;
        }

        this.goTo(this.currentStep + 1);
      });
    }
  }

  _validateStep2() {
    const url = this.context.querySelector("#ik_wizard_url_endpoint");
    const pub = this.context.querySelector("#ik_wizard_public_key");
    const priv = this.context.querySelector("#ik_wizard_private_key");
    const error = this.step2ErrorEl;

    const urlOk = url && url.value && url.value.trim().length > 0;
    const pubOk = pub && pub.value && pub.value.trim().length > 0;
    const privOk = priv && priv.value && priv.value.trim().length > 0;
    const ok = urlOk && pubOk && privOk;

    if (error) {
      if (!ok) {
        this._clearStep2FieldErrors();
        this._setStep2Error(this.step2DefaultErrorMessage);
      }
      error.hidden = ok;
    }

    return ok;
  }

  _clearStep2FieldErrors() {
    if (!this.step2FieldErrorEls) {
      return;
    }
    Object.values(this.step2FieldErrorEls).forEach((el) => {
      if (!el) {
        return;
      }
      el.textContent = "";
      el.hidden = true;
    });
  }

  _setStep2FieldErrors(fieldErrors) {
    this._clearStep2FieldErrors();
    if (!fieldErrors || typeof fieldErrors !== "object") {
      return;
    }
    Object.entries(fieldErrors).forEach(([key, message]) => {
      const el = this.step2FieldErrorEls ? this.step2FieldErrorEls[key] : null;
      if (!el || !message) {
        return;
      }
      el.textContent = message;
      el.hidden = false;
    });
  }

  _isTestConnectionSuccess(result) {
    if (!result) {
      return false;
    }
    if (result.ok === true) {
      return true;
    }
    if (result.type === "connection_success") {
      return true;
    }
    if (result.code === "connection_success") {
      return true;
    }
    return false;
  }

  _areStep2FieldsFilled() {
    const payload = this._getWizardPayload();
    return (
      payload.urlEndpoint &&
      payload.urlEndpoint.trim().length > 0 &&
      payload.publicKey &&
      payload.publicKey.trim().length > 0 &&
      payload.privateKey &&
      payload.privateKey.trim().length > 0
    );
  }

  _onStep2InputChange() {
    if (this.currentStep !== 2) {
      return;
    }

    this.step2ValidatedPayloadKey = null;
    this._clearStep2FieldErrors();

    if (this.step2DebounceTimer) {
      clearTimeout(this.step2DebounceTimer);
      this.step2DebounceTimer = null;
    }

    if (!this._areStep2FieldsFilled()) {
      this._setStep2Error(null);
      this._clearStep2FieldErrors();
      return;
    }

    const payload = this._getWizardPayload();
    const expectedKey = this._getWizardPayloadKey(payload);
    this.step2DebounceTimer = setTimeout(async () => {
      await this._testConnectionDebounced(expectedKey);
    }, this.step2DebounceMs);
  }

  async _testConnectionDebounced(expectedKey) {
    if (this.currentStep !== 2) {
      return;
    }

    const currentPayload = this._getWizardPayload();
    const currentKey = this._getWizardPayloadKey(currentPayload);
    if (currentKey !== expectedKey) {
      return;
    }

    if (this.isTesting) {
      return;
    }

    this.isTesting = true;
    if (this.buttonNext) {
      this.buttonNext.disabled = true;
    }

    try {
      const result = await this._postTestConnection();
      const latestPayload = this._getWizardPayload();
      const latestKey = this._getWizardPayloadKey(latestPayload);
      if (latestKey !== expectedKey || this.currentStep !== 2) {
        return;
      }

      if (this._isTestConnectionSuccess(result)) {
        this.step2ValidatedPayloadKey = expectedKey;
        this._clearStep2FieldErrors();
        this._setStep2Error(null);
        return;
      }

      this.step2ValidatedPayloadKey = null;
      this._setStep2FieldErrors(
        result && result.fieldErrors ? result.fieldErrors : null,
      );
      const message =
        result && result.message
          ? result.message
          : "Unable to verify connection. Please check your settings.";
      this._setStep2Error(message);
    } catch (err) {
      this.step2ValidatedPayloadKey = null;
      this._clearStep2FieldErrors();
      if (window && window.console && window.console.error) {
        window.console.error(err);
      }
      this._setStep2Error("Unable to verify connection. Please try again.");
    } finally {
      this.isTesting = false;
      if (this.buttonNext) {
        this.buttonNext.disabled = false;
      }
    }
  }

  _setStep2Error(message) {
    if (!this.step2ErrorEl) {
      return;
    }
    if (!message) {
      this.step2ErrorEl.hidden = true;
      return;
    }
    const p = this.step2ErrorEl.querySelector("p");
    if (p) {
      p.textContent = message;
    } else {
      this.step2ErrorEl.textContent = message;
    }
    this.step2ErrorEl.hidden = false;
  }

  _ensureNonceMiddleware(data) {
    if (!this.nonceMiddlewareSet && data && data.saveNonce) {
      apiFetch.use(apiFetch.createNonceMiddleware(data.saveNonce));
      this.nonceMiddlewareSet = true;
    }
  }

  _getWizardPayload() {
    const urlEndpointInput = this.context.querySelector(
      "#ik_wizard_url_endpoint",
    );
    const publicKeyInput = this.context.querySelector("#ik_wizard_public_key");
    const privateKeyInput = this.context.querySelector(
      "#ik_wizard_private_key",
    );

    return {
      urlEndpoint: urlEndpointInput ? urlEndpointInput.value.trim() : "",
      publicKey: publicKeyInput ? publicKeyInput.value.trim() : "",
      privateKey: privateKeyInput ? privateKeyInput.value.trim() : "",
    };
  }

  _getWizardPayloadKey(payload) {
    if (!payload) {
      return "";
    }
    return `${payload.urlEndpoint || ""}|${payload.publicKey || ""}|${payload.privateKey || ""}`;
  }

  async _testConnectionAndContinue() {
    if (this.isTesting) {
      return;
    }
    this.isTesting = true;

    if (this.buttonNext) {
      this.buttonNext.disabled = true;
    }

    try {
      const result = await this._postTestConnection();
      if (this._isTestConnectionSuccess(result)) {
        this._clearStep2FieldErrors();
        this._setStep2Error(null);
        this.goTo(3);
        return;
      }

      this._setStep2FieldErrors(
        result && result.fieldErrors ? result.fieldErrors : null,
      );
      const message =
        result && result.message
          ? result.message
          : "Unable to verify connection. Please check your settings.";
      this._setStep2Error(message);
    } catch (err) {
      if (window && window.console && window.console.error) {
        window.console.error(err);
      }
      this._clearStep2FieldErrors();
      this._setStep2Error("Unable to verify connection. Please try again.");
    } finally {
      this.isTesting = false;
      if (this.buttonNext) {
        this.buttonNext.disabled = false;
      }
    }
  }

  async _postTestConnection() {
    const data =
      window.ikData && window.ikData.wizard ? window.ikData.wizard : null;
    if (!data || !data.testURL) {
      throw new Error("Missing wizard testURL");
    }

    this._ensureNonceMiddleware(data);
    const payload = this._getWizardPayload();

    return await apiFetch({
      url: data.testURL,
      method: "POST",
      data: payload,
    });
  }

  async _saveWizardAndContinue() {
    if (this.isSaving) {
      return;
    }
    this.isSaving = true;

    if (this.buttonNext) {
      this.buttonNext.disabled = true;
    }

    try {
      await this._postWizard();
      this.goTo(4);
    } catch (err) {
      if (window && window.console && window.console.error) {
        window.console.error(err);
      }
      if (window && window.alert) {
        window.alert("Unable to save wizard settings. Please try again.");
      }
    } finally {
      this.isSaving = false;
      if (this.buttonNext) {
        this.buttonNext.disabled = false;
      }
    }
  }

  async _postWizard() {
    const data =
      window.ikData && window.ikData.wizard ? window.ikData.wizard : null;
    if (!data || !data.saveURL) {
      throw new Error("Missing wizard saveURL");
    }

    this._ensureNonceMiddleware(data);
    const payload = this._getWizardPayload();

    await apiFetch({
      url: data.saveURL,
      method: "POST",
      data: payload,
    });
  }

  goTo(step, options = {}) {
    const { focusPanel = true } = options;
    const nextStep = this._clampStep(step);
    this.currentStep = nextStep;

    this.tabs.forEach((tab) => {
      const isActive = parseInt(tab.dataset.step, 10) === nextStep;
      tab.classList.toggle("is-active", isActive);
      tab.setAttribute("aria-selected", isActive ? "true" : "false");
      tab.setAttribute("tabindex", isActive ? "0" : "-1");
    });

    this.panels.forEach((panel) => {
      const isActive = parseInt(panel.dataset.step, 10) === nextStep;
      panel.classList.toggle("is-active", isActive);
      panel.hidden = !isActive;
      if (isActive && focusPanel) {
        panel.focus();
      }
    });

    this._updateControls();
    this._updateIndicator();
  }

  _updateIndicator() {
    if (!this.stepIndicator) {
      return;
    }
    const current = Math.min(this.currentStep, this.visibleMaxStep);
    this.stepIndicator.textContent = `${current}/${this.visibleMaxStep}`;
  }

  _updateControls() {
    const isSuccess = this.currentStep >= this.maxStep;
    const isFirst = this.currentStep <= 1;

    if (this.buttonBack) {
      this.buttonBack.hidden = isFirst || isSuccess;
    }

    if (this.buttonNext) {
      this.buttonNext.hidden = isSuccess;
    }

    if (this.buttonFinish) {
      this.buttonFinish.hidden = !isSuccess;
    }
  }
}

const initWizard = () => {
  const contexts = document.querySelectorAll(".ik-wizard");
  if (!contexts.length) {
    return;
  }
  contexts.forEach((context) => {
    if (context) {
      new Wizard(context);
    }
  });
};

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initWizard);
} else {
  initWizard();
}

export default Wizard;
