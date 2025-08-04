(async function () {
  let innertubeKey, clientVersion, continuationToken;

  async function renderItems(items) {
    const container = document.getElementById("results");
    items.forEach((item) => {
      const card = document.createElement("div");
      card.className = "card";
      const img = document.createElement("img");
      img.src = item.thumbnail;
      img.alt = item.title;
      const title = document.createElement("h3");
      title.textContent = item.title;
      const btnGroup = document.createElement("div");
      btnGroup.className = "buttons";
      const embed = document.createElement("a");
      embed.href = `viewer.php?id=${encodeURIComponent(
        item.videoId
      )}&mode=embed`;
      embed.textContent = "Watch (embed)";
      const local = document.createElement("a");
      local.href = `viewer.php?id=${encodeURIComponent(
        item.videoId
      )}&mode=local`;
      local.textContent = "Play (local)";
      btnGroup.appendChild(embed);
      btnGroup.appendChild(local);
      card.appendChild(img);
      card.appendChild(title);
      card.appendChild(btnGroup);
      container.appendChild(card);
    });
  }

  document
    .getElementById("searchForm")
    .addEventListener("submit", async function (e) {
      e.preventDefault();
      const q = document.getElementById("searchInput").value.trim();
      if (!q) return;
      document.getElementById("message").textContent = "";
      document.getElementById("results").innerHTML = "";
      document.getElementById("loadMore").style.display = "none";

      try {
        const res = await fetch(`search.php?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (data.error) {
          document.getElementById("message").textContent = data.error;
          return;
        }
        innertubeKey = data.innertube_key;
        clientVersion = data.client_version;
        continuationToken = data.continuation;
        if (!data.results.length) {
          document.getElementById("message").textContent = "No results found.";
          return;
        }
        renderItems(data.results);

        if (continuationToken) {
          document.getElementById("loadMore").style.display = "block";
        }
      } catch (err) {
        document.getElementById("message").textContent =
          "Error fetching initial results.";
      }
    });

  document
    .getElementById("loadMore")
    .addEventListener("click", async function () {
      if (!continuationToken) return;
      const btn = this;
      btn.disabled = true;
      btn.textContent = "Loading...";
      document.getElementById("message").textContent = "";

      try {
        const url = `load_more.php?token=${encodeURIComponent(
          continuationToken
        )}&key=${encodeURIComponent(innertubeKey)}&version=${encodeURIComponent(
          clientVersion
        )}`;
        const res = await fetch(url);
        const data = await res.json();
        if (data.error) {
          document.getElementById("message").textContent =
            "Error fetching more results.";
        } else {
          renderItems(data.results);
          continuationToken = data.continuation;
          if (!continuationToken) {
            btn.style.display = "none";
          }
        }
      } catch (err) {
        document.getElementById("message").textContent =
          "Error fetching more results.";
      }
      btn.disabled = false;
      btn.textContent = "Load More";
    });
})();
