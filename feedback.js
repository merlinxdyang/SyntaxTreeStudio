(() => {
  document.querySelectorAll("[data-feedback-insert]").forEach((button) => {
    button.addEventListener("click", () => {
      const form = button.closest("form");
      const textarea = form?.querySelector("[data-feedback-message]");
      if (!textarea) return;
      insertSnippet(textarea, button.dataset.feedbackInsert || "");
    });
  });

  document.querySelectorAll("[data-feedback-attachment]").forEach((input) => {
    input.addEventListener("change", () => {
      const file = input.files?.[0];
      const tooLarge = file && file.size > 5 * 1024 * 1024;
      input.setCustomValidity(tooLarge ? input.dataset.sizeError || "The image must be 5 MB or smaller." : "");
      if (tooLarge) input.reportValidity();
    });
  });

  function insertSnippet(textarea, command) {
    const snippets = markdownSnippets();
    const snippet = snippets[command] || snippets.bold;
    const start = textarea.selectionStart ?? textarea.value.length;
    const end = textarea.selectionEnd ?? textarea.value.length;
    const selected = textarea.value.slice(start, end) || snippet.placeholder;
    const next = snippet.wrap(selected);
    textarea.setRangeText(next, start, end, "select");
    textarea.focus();
  }

  function markdownSnippets() {
    return {
      bold: { placeholder: "bold text", wrap: (text) => `**${text}**` },
      italic: { placeholder: "italic text", wrap: (text) => `*${text}*` },
      link: { placeholder: "link text", wrap: (text) => `[${text}](https://example.com)` },
      quote: { placeholder: "quote", wrap: (text) => `> ${text}` },
      code: { placeholder: "code", wrap: (text) => `\`${text}\`` },
      list: { placeholder: "item", wrap: (text) => `- ${text}` },
    };
  }

})();
