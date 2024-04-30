// Attach click event listeners to buttons for SQL evaluation.
document.getElementById("evaluateSqlButton").addEventListener("click", function () {
  evaluateCode(true); // Evaluate SQL code.
});

document.getElementById("executeSqlButton").addEventListener("click", function () {
  evaluateCode(false); // Execute SQL code.
});

// Function to either evaluate or execute code based on the 'evaluate' parameter.
function evaluateCode(evaluate) {
  // Extract necessary IDs from DOM elements.
  var attemptId = JSON.parse(document.getElementById("attemptIdContainer").textContent);
  var questionId = JSON.parse(document.getElementById("questionIdContainer").textContent);
  var cmid = JSON.parse(document.getElementById("cmidContainer").textContent);

  // Get SQL from CodeMirror instance; if no text is selected, use all text.
  var codeMirrorInstance = document.querySelector(".CodeMirror").CodeMirror;
  var sql = codeMirrorInstance.getSelection().trim() || codeMirrorInstance.getValue().trim();

  // Define URL and create form data for the POST request.
  var url = "/mod/sqlab/update_grade.php";
  var formData = new URLSearchParams({
    attempt: attemptId,
    question: questionId,
    cmid: cmid,
    sql_code: sql,
    evaluate: evaluate,
  });

  // Send POST request to update the grade.
  fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: formData,
  })
  .then(response => {
    if (!response.ok) throw new Error("Network response was not ok: " + response.statusText);
    return response.json();
  })
  .then(data => {
    if (!data.success) {
      console.error("Error handling grades:", data.error);
    }
  })
  .catch(error => {
    console.error("Error during fetch operation:", error.message);
  });
}
