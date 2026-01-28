class f{constructor(t,e,n){var a;this.form=t,this.data=e,this.paymentArgs=(e==null?void 0:e.payment_args)||{},this.paymentLoader=n,this.container=document.querySelector(".fluent-cart-checkout_embed_payment_container_authorize_dot_net"),this.$t=this.translate.bind(this),this.submitButton=(a=window.fluentcart_checkout_vars)==null?void 0:a.submit_button,window.is_authorize_dot_net_ready=!1}translate(t){var n;return(((n=window.fct_authorize_dot_net_data)==null?void 0:n.translations)||{})[t]||t}async init(){var t,e;if(window.is_authorize_dot_net_ready=!1,!!this.container){if(!((t=this.paymentArgs)!=null&&t.client_key)||!((e=this.paymentArgs)!=null&&e.api_login_id)){this.container.innerHTML='<div class="fct-authorize-net-error">'+this.$t("Authorize.Net payment form failed to load.")+"</div>";return}this.initAuthorizeNetAcceptUI(),window.fluentCartAuthorizeNetInstance=this,this.registerEvents()}}async initAuthorizeNetAcceptUI(){var h;window.is_authorize_dot_net_ready=!1;const t=this.data.payment_args||{},e=t.api_login_id||"",n=t.client_key||"",a=t.enable_echeck||!1,o=t.accept_ui_form_btn_txt||"Pay now",d=t.accept_ui_form_header_txt||"Card Informations",s=t.accept_ui_button_text||((h=this.submitButton)==null?void 0:h.text)||"Place Order",p=t.accept_ui_button_background_color||"#0F4B8D";if(t.accept_ui_button_hover_color,t.show_billing_address,t.require_billing_address,!document.getElementById("fct-authorize-net-button-styles")){const i=document.createElement("style");i.id="fct-authorize-net-button-styles",i.textContent=`
                .fct-authorize-net-container .AcceptUI {
                    width: 100%;
                    padding: 12px 24px;
                    font-size: 16px;
                    font-weight: 600;
                    color: #ffffff;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    margin-top: 10px;
                    display: block;
                    text-align: center;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                }
                .fct-authorize-net-container .AcceptUI:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
                }
                .fct-authorize-net-container .AcceptUI:active {
                    transform: translateY(0);
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                }
            `,document.head.appendChild(i)}const _=`
            <div class="fct-authorize-net-container">
                <input type="hidden" name="dataValue" id="dataValue" />
                <input type="hidden" name="dataDescriptor" id="dataDescriptor" />
                <button 
                    type="button"
                    class="AcceptUI"
                    data-apiLoginID="${e}"
                    data-clientKey="${n}"
                    data-acceptUIFormBtnTxt="${o}"
                    data-acceptUIFormHeaderTxt="${d}"
                    data-responseHandler="fluentCartAuthorizeNetResponseHandler"
                    style="background-color: ${p};">
                   ${s}
                </button>
                <div class="fct-authorize-net-errors" role="alert" style="color: red; font-size: 14px;  padding: 10px;"></div>
            </div>
        `;this.container&&(this.container.innerHTML=_);const u=document.getElementById("dataValue"),l=document.getElementById("dataDescriptor");u&&(u.value=""),l&&(l.value="");const c=this.container.querySelector(".AcceptUI");c&&a?c.setAttribute("data-paymentOptions",'{"showCreditCard": true, "showBankAccount": true}'):c&&c.setAttribute("data-paymentOptions",'{"showCreditCard": true}');try{const i=document.querySelector("script[data-authorize-net-accept-ui]");i&&(i.remove(),window.AcceptUI=void 0),await this.ensureAcceptUIScript()}catch(i){this.showError((i==null?void 0:i.message)||this.$t("Authorize.Net payment form failed to load."));return}}ensureAcceptUIScript(){return new Promise((t,e)=>{var o;if(window.AcceptUI){t();return}const n=document.querySelector("script[data-authorize-net-accept-ui]");if(n){n.addEventListener("load",t,{once:!0}),n.addEventListener("error",()=>e(new Error(this.$t("Authorize.Net payment form failed to load."))),{once:!0});return}const a=document.createElement("script");a.src=((o=this.paymentArgs)==null?void 0:o.mode)==="test"?"https://jstest.authorize.net/v3/AcceptUI.js":"https://js.authorize.net/v3/AcceptUI.js",a.dataset.authorizeNetAcceptUi="true",a.onload=t,a.onerror=()=>e(new Error(this.$t("Authorize.Net payment form failed to load."))),document.head.appendChild(a)})}registerEvents(){window.fluentCartAuthorizeNetValidateHandler&&window.removeEventListener("fluent_cart_validate_checkout_authorize_dot_net",window.fluentCartAuthorizeNetValidateHandler),window.fluentCartAuthorizeNetValidateHandler=t=>{if(window.is_authorize_dot_net_ready)return;const e=document.querySelector(".AcceptUI");e?setTimeout(()=>{e.click()},50):this.showError(this.$t("Authorize.Net payment form failed to load."))},window.addEventListener("fluent_cart_validate_checkout_authorize_dot_net",window.fluentCartAuthorizeNetValidateHandler)}handleResponse(t){if(t.messages.resultCode==="Error"){const a=t.messages.message[0].text||this.$t("Tokenization failed. Please verify the details.");this.showError(a),window.is_authorize_dot_net_ready=!1,this.showError(a);return}const e=document.getElementById("dataValue"),n=document.getElementById("dataDescriptor");e&&(e.value=t.opaqueData.dataValue),n&&(n.value=t.opaqueData.dataDescriptor),window.is_authorize_dot_net_ready=!0,this.triggerCheckout()}triggerCheckout(){const t=document.querySelector("[data-fluent-cart-checkout-page-checkout-button]");t&&t.click()}showError(t){const e=document.querySelector(".fct-authorize-net-errors");e&&(e.textContent=t,e.style.color="#dc2626")}}window.fluentCartAuthorizeNetResponseHandler=function(r){const t=window.fluentCartAuthorizeNetInstance;t&&t.handleResponse(r)};window.addEventListener("fluent_cart_load_payments_authorize_dot_net",r=>{var e,n,a;window.dispatchEvent(new CustomEvent("fluent_cart_payment_method_loading",{detail:{payment_method:"authorize_dot_net"}})),(e=window.fluentcart_checkout_vars)==null||e.submit_button;const t=document.querySelector(".fluent-cart-checkout_embed_payment_container_authorize_dot_net");t&&(t.innerHTML='<div id="fct_loading_payment_processor">'+(((a=(n=window.fct_authorize_dot_net_data)==null?void 0:n.translations)==null?void 0:a["Processing payment..."])||"Loading...")+"</div>"),fetch(r.detail.paymentInfoUrl,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":r.detail.nonce},credentials:"include"}).then(o=>o.json()).then(o=>{if(window.dispatchEvent(new CustomEvent("fluent_cart_payment_method_loading_success",{detail:{payment_method:"authorize_dot_net"}})),o.status!=="success"){const s=o.message||"Unable to load Authorize.Net payment fields.";t&&(t.innerHTML='<div class="fct-authorize-net-error" style="color: red; font-size: 14px;  padding: 10px;">'+s+"</div>");return}new f(r.detail.form,o,r.detail.paymentLoader).init()}).catch(()=>{t&&(t.innerHTML='<div class="fct-authorize-net-error" style="color: red; font-size: 14px;  padding: 10px;">Failed to load Authorize.Net checkout.</div>')})});
