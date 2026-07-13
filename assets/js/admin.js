(function () {
  document.addEventListener("DOMContentLoaded", function () {
    if (window.jQuery && window.jQuery.fn.wpColorPicker) {
      window.jQuery(".tracsoft-lb-color-field").wpColorPicker();
    }

    document.querySelectorAll(".tracsoft-lb-admin textarea").forEach(function (textarea) {
      textarea.addEventListener("input", function () {
        textarea.style.height = "auto";
        textarea.style.height = Math.min(textarea.scrollHeight, 520) + "px";
      });
    });
  });
})();
