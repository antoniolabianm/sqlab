// Attach event listener after the DOM is fully loaded.
document.addEventListener("DOMContentLoaded", function () {
  // Get all elements with class 'accordion-title'.
  var accordionTitles = document.querySelectorAll(".accordion-title");

  accordionTitles.forEach(function (accordionTitle) {
    var accordionContent = accordionTitle.nextElementSibling; // Get the content element.
    accordionContent.style.display = "none"; // Hide content initially.
    accordionTitle.classList.remove("open"); // Remove 'open' class initially.

    // Add click event to toggle accordion open/close.
    accordionTitle.addEventListener("click", function () {
      var isHidden = accordionContent.style.display === "none";
      accordionContent.style.display = isHidden ? "block" : "none";
      accordionTitle.classList.toggle("open"); // Toggle visual state.
    });
  });
});
