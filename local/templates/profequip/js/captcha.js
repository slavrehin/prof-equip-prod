function onloadFunction() {
    if (!window.smartCaptcha) {
        return;
    }

    window.smartCaptcha.render(document.querySelector(".captcha-container-footer"), {
        sitekey: 'ysc1_UIwDIZX4s5peooFanDbgUdhicJUCkPZ3AsQJ559m1ffac82e',
        invisible: true, 
        callback: callback,
        test: true,
    });
}

window.captchaSuccess = false;

function callback(token) {
    console.log("12345");
  if (typeof token === "string" && token.length > 0) {
      window.captchaSuccess = true;
      let event = new Event("changeCaptchaSuccess", {bubbles: true});
      document.querySelector('body').dispatchEvent(event);
  }
  console.log(token);
}
