(() => {
  const dialog = document.getElementById("alipayDialog");
  const openButton = document.querySelector("[data-alipay-open]");
  const closeButton = document.querySelector("[data-alipay-close]");

  if (!(dialog instanceof HTMLDialogElement) || !(openButton instanceof HTMLButtonElement)) {
    return;
  }

  openButton.addEventListener("click", () => {
    dialog.showModal();
    closeButton?.focus();
  });

  closeButton?.addEventListener("click", () => {
    dialog.close();
  });

  dialog.addEventListener("click", (event) => {
    const bounds = dialog.getBoundingClientRect();
    const clickedOutside =
      event.clientX < bounds.left ||
      event.clientX > bounds.right ||
      event.clientY < bounds.top ||
      event.clientY > bounds.bottom;

    if (clickedOutside) {
      dialog.close();
    }
  });

  dialog.addEventListener("close", () => {
    openButton.focus();
  });
})();
