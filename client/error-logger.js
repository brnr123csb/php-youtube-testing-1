// Create or select an error message container in the DOM
const messageEl = document.getElementById("message") || createMessageElement();

function createMessageElement() {
  const el = document.createElement("div");
  el.id = "message";
  el.style.color = "red";
  el.style.margin = "10px 0";
  const container = document.body; // or wherever appropriate
  container.insertBefore(el, container.firstChild);
  return el;
}

// Log an error message (replace or append)
function logError(msg, append = false) {
  if (append) {
    messageEl.textContent += `\n${msg}`;
  } else {
    messageEl.textContent = msg;
  }
}

// Visualize
function clearError() {
  messageEl.textContent = "";
}

export { logError, clearError };
