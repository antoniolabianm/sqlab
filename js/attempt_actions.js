// Wait for jQuery to be ready.
$(document).ready(function () {
  // Get DOM elements.
  var attemptButton = document.getElementById("attemptButton");
  var cmidContainer = document.getElementById("cmidContainer");
  var cmid = JSON.parse(cmidContainer.textContent); // Parse cmid.
  var passwordRequired = $("#passwordRequired").val() === "1"; // Check password requirement.

  // Function to create a new attempt using an AJAX POST request.
  function createAttempt(cmid) {
    $.ajax({
      url: "/mod/sqlab/create_attempt.php",
      type: "POST",
      data: { action: "create_new_attempt", cmid: cmid },
      success: function (response) {
        var result = JSON.parse(response); // Parse JSON response.
        if (result.status === "success") {
          // Redirect to new attempt URL.
          window.location.href = `/mod/sqlab/attempt.php?attempt=${result.newattemptid}&cmid=${cmid}&page=0`;
        } else {
          console.error(result.message); // Log errors.
        }
      },
      error: function (xhr, status, error) {
        console.error("Error starting new attempt: " + error); // Log AJAX errors.
      },
    });
  }

  // Event listener for the attempt button.
  if (attemptButton) {
    attemptButton.addEventListener("click", function () {
      var action = this.getAttribute("data-action"); // Retrieve action type.
      if (action === "start") {
        if (passwordRequired) {
          $("#modalDialog").show(); // Show password modal.
        } else {
          createAttempt(cmid); // Create attempt directly.
        }
      } else if (action === "continue") {
        window.location.href = continueAttemptUrl; // Redirect to ongoing attempt.
      }
    });
  }
});
