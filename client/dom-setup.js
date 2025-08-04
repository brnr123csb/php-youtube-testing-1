// Create sentinel element for infinite scroll
const sentinel = document.createElement("div");
sentinel.id = "sentinel";
sentinel.style.height = "20px";
document
  .getElementById("results")
  .parentNode.insertBefore(
    sentinel,
    document.getElementById("results").nextSibling
  );

// Create loading indicator element
const loadingIndicator = document.createElement("div");
loadingIndicator.id = "loading";
loadingIndicator.style.display = "none";
loadingIndicator.style.textAlign = "center";
loadingIndicator.style.margin = "20px 0";
loadingIndicator.style.color = "#f5e146";
loadingIndicator.textContent = "Loading more videos...";
sentinel.parentNode.insertBefore(loadingIndicator, sentinel.nextSibling);

export { sentinel, loadingIndicator };
