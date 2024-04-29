// Attach event listeners to execute and evaluate SQL buttons.
document.getElementById("executeSqlButton").addEventListener("click", function() { executeSql('execute'); });
document.getElementById("evaluateSqlButton").addEventListener("click", function() { executeSql('evaluate'); });

// Function to execute SQL from the CodeMirror editor.
function executeSql(action) {
  // Retrieve CodeMirror instance and SQL text.
  var codeMirrorInstance = document.querySelector(".CodeMirror").CodeMirror;
  var sql = codeMirrorInstance.getSelection().trim() || codeMirrorInstance.getValue().trim();

  // Get the attempt ID from the DOM.
  var attemptid = JSON.parse(document.getElementById("attemptIdContainer").textContent);

  // Prepare the results container.
  var resultsContainer = document.getElementById("sqlQueryResults");
  resultsContainer.innerHTML = "";

  // Exit if no SQL is provided.
  if (!sql) {
    resultsContainer.innerHTML += '<div class="info-message">There is no SQL query(s) to execute.</div>';
    return;
  }

  // Data for POST request.
  var data = { sql: sql, attempt: attemptid, action: action };

  // Send POST request to execute SQL.
  fetch("/mod/sqlab/execute_sql.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data),
  })
  .then(response => {
    if (!response.ok) throw new Error(`HTTP Error - Status: ${response.status}`);
    return response.json();
  })
  .then(data => {
    // Display query results or errors.
    if (data.status === "error") {
      resultsContainer.innerHTML += `<div class="error-message">${data.message}</div>`;
    } else {
      data.results.forEach(result => {
        if (result.type.toUpperCase() === "SELECT") {
          var message = result.data && result.data.length > 0 ? "The selected data are:" : "No data returned.";
          var successMessage = `<div class="success-message">Query '${result.type}' successfully executed. ${message}</div>`;
          var resultTable = createResultTable(result.data);
          resultsContainer.innerHTML += successMessage;
          resultsContainer.appendChild(resultTable);
        } else {
          resultsContainer.innerHTML += `<div class="success-message">Query '${result.type}' successfully executed. ${result.affectedRows} affected rows.</div>`;
        }
      });
    }
  })
  .catch(error => {
    resultsContainer.innerHTML += `<div class="error-message">Error in the request - ${error.message}</div>`;
  });
}

// Function to display SQL query results in a table.
function createResultTable(data) {
  var table = document.createElement("table");
  table.className = "sql-results";
  var thead = document.createElement("thead");
  var headerRow = document.createElement("tr");

  // Create table headers.
  if (data.length > 0) {
    Object.keys(data[0]).forEach(key => {
      var header = document.createElement("th");
      header.className = "sql-results-header";
      header.innerText = key;
      headerRow.appendChild(header);
    });
    thead.appendChild(headerRow);
    table.appendChild(thead);

    // Create table body.
    var tbody = document.createElement("tbody");
    data.forEach(row => {
      var tableRow = document.createElement("tr");
      tableRow.className = "sql-results-row";
      Object.values(row).forEach(value => {
        var td = document.createElement("td");
        td.className = "sql-results-data";
        td.innerText = value;
        tableRow.appendChild(td);
      });
      tbody.appendChild(tableRow);
    });
    table.appendChild(tbody);
  }
  return table;
}
