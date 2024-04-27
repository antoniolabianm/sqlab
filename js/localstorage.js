// Save a response to local storage.
function saveResponse(questionId, response) {
  localStorage.setItem("response_" + questionId, response);
}

// Initialize editor once DOM is fully loaded.
document.addEventListener("DOMContentLoaded", function () {
  // Check if the editor is initialized.
  if (editor) {
    // Get question ID from editor's text area.
    const questionId = editor.getTextArea().getAttribute("data-question-id").split("-")[1];

    // Load and set saved response if it exists.
    const savedResponse = localStorage.getItem("response_" + questionId);
    if (savedResponse) {
      editor.setValue(savedResponse);
    }

    // Save the response when leaving the page.
    window.onbeforeunload = function () {
      saveResponse(questionId, editor.getValue());
    };
  } else {
    console.error("CodeMirror has not been initialized.");
  }
});
