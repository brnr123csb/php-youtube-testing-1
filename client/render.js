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
      embed.href = `viewer.php?id=${encodeURIComponent(item.videoId)}&mode=embed`;
      embed.textContent = "Watch (embed)";
  
      const local = document.createElement("a");
      local.href = `viewer.php?id=${encodeURIComponent(item.videoId)}&mode=local`;
      local.textContent = "Play (local)";
  
      btnGroup.appendChild(embed);
      btnGroup.appendChild(local);
  
      card.appendChild(img);
      card.appendChild(title);
      card.appendChild(btnGroup);
  
      container.appendChild(card);
    });
  }
  
  export { renderItems };
  
