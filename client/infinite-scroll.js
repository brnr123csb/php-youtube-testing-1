import { loadingIndicator } from './dom-setup.js';
import { renderItems } from './render.js';
import { continuationToken, innertubeKey, clientVersion, isLoading, setIsLoading, setContinuationToken } from './state.js';

const observer = new IntersectionObserver(
  (entries) => {
    if (entries[0].isIntersecting && continuationToken.value && !isLoading.value) {
      loadMore();
    }
  },
  { threshold: 0.1 }
);

async function loadMore() {
  if (isLoading.value || !continuationToken.value) return;

  setIsLoading(true);
  loadingIndicator.style.display = "block";

  try {
    const url = `load_more.php?token=${encodeURIComponent(
      continuationToken.value
    )}&key=${encodeURIComponent(innertubeKey.value)}&version=${encodeURIComponent(
      clientVersion.value
    )}`;

    const res = await fetch(url);
    const data = await res.json();

    if (data.error) {
      document.getElementById("message").textContent =
        "Error loading more videos";
    } else {
      await renderItems(data.results);
      setContinuationToken(data.continuation);

      if (!data.continuation) {
        observer.disconnect();
        loadingIndicator.style.display = "none";
      }
    }
  } catch (err) {
    document.getElementById("message").textContent =
      "Error loading more videos";
  } finally {
    setIsLoading(false);
    loadingIndicator.style.display = "none";
  }
}

function initInfiniteScroll() {
  // Nothing needed here yet; observing handled in search-handler.js
}

export { observer, loadMore, initInfiniteScroll };
