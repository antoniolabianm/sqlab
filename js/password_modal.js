// jQuery selectors for DOM elements.
var modal = $("#modalDialog");
var btn = $("#attemptButton");
var closeButtons = $(".modal-close-btn, .modal .close");

// Initialize event handlers.
$(document).ready(function () {
  // Show modal on button click if password is required.
  btn.on("click", function () {
    if ($("#passwordRequired").val() === "1") {
      modal.show();
    }
  });

  // Fade out modal on close button clicks.
  closeButtons.on("click", function () {
    modal.fadeOut();
  });

  // Fade out modal by clicking outside of it.
  $("body").on("click", function (e) {
    if ($(e.target).hasClass("modal")) {
      modal.fadeOut();
    }
  });

  // Toggle password visibility.
  $("#togglePassword").click(function () {
    var passwordField = $("#modalPassword");
    var type = passwordField.attr("type") === "password" ? "text" : "password";
    passwordField.attr("type", type);
    $(this).find("i").toggleClass("fa-eye fa-eye-slash");
  });

  // Submit password form.
  $("#passwordForm").submit(function (e) {
    e.preventDefault();
    var password = $("#modalPassword").val().trim();

    if (!password) {
      showError(M.util.get_string("passwordempty", "sqlab"));
      return;
    }

    // Form data serialization with CMID.
    var formData = $(this).serialize() + "&cmid=" + $("#cmidContainer").text().trim();

    // Password verification via AJAX POST.
    $.ajax({
      type: "POST",
      url: "/mod/sqlab/password_check.php",
      data: formData,
      success: function (response) {
        var data = JSON.parse(response);
        if (data.status === "success") {
          createAttempt($("#cmidContainer").text().trim()); // Proceed to create attempt.
        } else {
          showError(data.message);
        }
      },
      error: function () {
        showError(M.util.get_string("ajaxerror", "sqlab"));
      },
    });
  });
});

// Display error messages in modal.
function showError(message) {
  $(".modal-body #passwordError").remove();
  var errorParagraph = $('<p id="passwordError" style="color: red; font-size: small; margin-top: 10px;"></p>');
  errorParagraph.text(message);
  $(".modal-body").append(errorParagraph);
}

// Create a new attempt with a POST request.
function createAttempt(cmid) {
  $.ajax({
    url: "/mod/sqlab/create_attempt.php",
    type: "POST",
    data: { action: "create_new_attempt", cmid: cmid },
    success: function (response) {
      var result = JSON.parse(response);
      if (result.status === "success") {
        var newAttemptUrl = `/mod/sqlab/attempt.php?attempt=${result.newattemptid}&cmid=${cmid}&page=0`;
        window.location.href = newAttemptUrl;
      } else {
        showError(result.message);
      }
    },
    error: function (xhr, status, error) {
      showError("Error starting new attempt: " + error);
    },
  });
}
