import { renderItems } from './render.js';
import { observer, loadMore } from './infinite-scroll.js';
import { sentinel, loadingIndicator } from './dom-setup.js';
import { innertubeKey, clientVersion, continuationToken, isLoading, setInnertubeKey, setClientVersion, setContinuationToken, setIsLoading } from './state.js';

const searchForm = document.getElementById("searchForm");
const searchInput = document.getElementById("searchInput");
const messageEl = document.getElementById("message");
const resultsEl = document.getElementById("results");

searchForm.addEventListener("submit", async function (e) {
  e.preventDefault();
  const q = searchInput.value.trim();
  if (!q) return;

  // Reset state and UI
  messageEl.textContent = "";
  resultsEl.innerHTML = "";
  observer.disconnect();
  setIsLoading(false);
  setContinuationToken(null);
  loadingIndicator.style.display = "none";

  try {
    const res = await fetch(`search.php?q=${encodeURIComponent(q)}`);
    const data = await res.json();

    if (data.error) {
      messageEl.textContent = data.error;
      return;
    }

    setInnertubeKey(data.innertube_key);
    setClientVersion(data.client_version);
    setContinuationToken(data.continuation);

    if (!data.results.length) {
      messageEl.textContent = "No results found.";
      return;
    }

    await renderItems(data.results);

    if (data.continuation) {
      observer.observe(sentinel);
    }
  } catch (err) {
    messageEl.textContent = "Error fetching initial results.";
  }
});
