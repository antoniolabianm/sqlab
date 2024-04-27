// Global variable for the CodeMirror editor.
var editor;

// Function to change the theme of the editor based on user selection.
function changeEditorTheme(newTheme) {
  if (editor) {
    editor.setOption("theme", newTheme);
    localStorage.setItem("editorTheme", newTheme); // Save the selected theme to localStorage.
  }
}

// Function to toggle fullscreen mode for the editor.
function toggleFullScreen(editor) {
  var isFullScreen = editor.getOption("fullScreen");
  editor.setOption("fullScreen", !isFullScreen);

  // Toggle visibility of the header and footer when in fullscreen mode.
  document.querySelector("nav.navbar").style.display = isFullScreen ? "flex" : "none";
  document.getElementById("page-footer").style.display = isFullScreen ? "flex" : "none";

  // Refresh editor after a delay to ensure it adjusts to the new layout.
  setTimeout(function () {
    editor.refresh();
  }, 100);
}

// Function to initialize CodeMirror with additional features.
function initializeCodeMirror() {
  var myTextarea = document.getElementById("myCodeMirror");

  if (myTextarea) {
    // Load saved preferences or use default values.
    var savedTheme = localStorage.getItem("editorTheme") || "default";
    var savedFontSize = localStorage.getItem("editorFontSize") || "16px";

    // Initialize CodeMirror on the textarea.
    editor = CodeMirror.fromTextArea(myTextarea, {
      mode: "text/x-pgsql",
      lineNumbers: true,
      lineWrapping: true,
      theme: savedTheme,
      matchBrackets: true,
      autoCloseBrackets: true,
      gutters: ["CodeMirror-linenumbers"],
      extraKeys: {
        "Ctrl-Space": "autocomplete",
        F11: function (cm) {
          toggleFullScreen(cm);
        },
        Esc: function (cm) {
          if (cm.getOption("fullScreen")) toggleFullScreen(cm);
        },
      },
    });

    // Set the initial font size of the editor and refresh.
    editor.getWrapperElement().style.fontSize = savedFontSize;
    editor.refresh();

    // Update the theme selector to reflect saved or default theme.
    var themeSelector = document.getElementById("themeSelector");
    if (themeSelector) {
      themeSelector.value = localStorage.getItem("editorTheme") ? savedTheme : "";
    }

    // Update the font size selector to reflect saved or default font size.
    var fontSizeSelector = document.getElementById("fontSizeSelector");
    if (fontSizeSelector) {
      fontSizeSelector.value = localStorage.getItem("editorFontSize") ? savedFontSize : "";
    }
  }
}

// Function to change the font size of the editor.
function changeFontSize(size) {
  if (editor) {
    editor.getWrapperElement().style.fontSize = size;
    editor.refresh();
    localStorage.setItem("editorFontSize", size); // Save the new font size to localStorage.
  }
}

// Event listener to handle theme changes from the user.
document.addEventListener("DOMContentLoaded", function () {
  var themeSelector = document.getElementById("themeSelector");
  if (themeSelector) {
    themeSelector.addEventListener("change", function () {
      changeEditorTheme(this.value);
    });
  }

  // Initialize the CodeMirror editor.
  initializeCodeMirror();
});
