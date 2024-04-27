// Attach event listener after DOM is fully loaded.
document.addEventListener("DOMContentLoaded", function () {
  // Retrieve user and query information from DOM.
  var userId = document.getElementById("resultDataUserId").textContent;
  var sql = document.getElementById("resultDataSql").textContent;
  var schemaName = document.getElementById("resultDataSchema").textContent;

  // Execute SQL and fetch results via POST request.
  fetch("/mod/sqlab/execute_context_resultdata.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      userId: userId,
      sql: sql,
      schemaName: schemaName,
      fetchResults: true,
    }),
  })
  .then(response => {
    if (!response.ok) throw new Error(`HTTP Error - Status: ${response.status}`);
    return response.json();
  })
  .then(data => {
    // Process and display fetched data.
    if (data.status === "error") {
      console.error("Error fetching data:", data.message);
    } else {
      var resultTable = createExpectedResultTable(data.results);
      document.getElementById("resultDataContainer").appendChild(resultTable);
    }
  })
  .catch(error => {
    console.error("Error in request:", error.message);
  });
});

// Function to create a table displaying SQL results.
function createExpectedResultTable(data) {
  var table = document.createElement("table");
  table.className = "sql-expectedresults-table";

  if (data && data.length) {
    var thead = document.createElement("thead");
    var headerRow = document.createElement("tr");

    // Create headers for the result table.
    Object.keys(data[0]).forEach(key => {
      var header = document.createElement("th");
      header.className = "sql-expectedresults-header";
      header.textContent = key;
      headerRow.appendChild(header);
    });
    thead.appendChild(headerRow);
    table.appendChild(thead);

    // Fill table with data rows.
    var tbody = document.createElement("tbody");
    data.forEach(row => {
      var tableRow = document.createElement("tr");
      tableRow.className = "sql-expectedresults-row";
      Object.values(row).forEach(value => {
        var td = document.createElement("td");
        td.className = "sql-expectedresults-data";
        td.textContent = value;
        tableRow.appendChild(td);
      });
      tbody.appendChild(tableRow);
    });
    table.appendChild(tbody);
  } else {
    // Add message if no data is returned.
    var noDataMsg = document.createElement("div");
    noDataMsg.className = "info-message";
    noDataMsg.textContent = "No data returned.";
    table.appendChild(noDataMsg);
  }
  return table;
}
