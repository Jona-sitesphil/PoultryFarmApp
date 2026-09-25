document.addEventListener("DOMContentLoaded", function () {
  // 1. Pwersahang linisin ang mga input para mawala ang auto-fill ng browser
  const inputsToClear = document.querySelectorAll(
    "input[type='text'], input[type='password']",
  );
  inputsToClear.forEach((input) => {
    input.value = "";
  });

  // 2. Password Toggle Function
  function setupToggle(toggleId, inputId) {
    const toggleIcon = document.getElementById(toggleId);
    const passwordInput = document.getElementById(inputId);

    if (toggleIcon && passwordInput) {
      toggleIcon.addEventListener("click", function () {
        const type =
          passwordInput.getAttribute("type") === "password"
            ? "text"
            : "password";
        passwordInput.setAttribute("type", type);
        this.classList.toggle("fa-eye");
        this.classList.toggle("fa-eye-slash");
      });
    }
  }

  setupToggle("togglePasswordLogin", "password");
  setupToggle("togglePasswordReg", "reg_password");
  setupToggle("togglePasswordConfirm", "confirm_password");
});
