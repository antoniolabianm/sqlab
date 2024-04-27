// Attach click event handler to "infoButton".
document.getElementById("infoButton").onclick = function () {
  // Get the "infoText" element.
  var infoText = document.getElementById("infoText");

  // Toggle visibility of the "infoText".
  if (infoText.style.display === "block") {
    infoText.style.display = "none"; // Hide the element.
    setTimeout(function () {
      infoText.style.opacity = 0; // Gradually reduce opacity.
    }, 10);
  } else {
    infoText.style.display = "block"; // Show the element.
    setTimeout(function () {
      infoText.style.opacity = 1; // Gradually increase opacity.
    }, 10);
  }
};
