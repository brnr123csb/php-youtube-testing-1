<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Your tube</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 20px;
      background: linear-gradient(135deg, #0f0f0f, #1a001a);
      color: #f0eaff;
    }

    h1 {
      text-align: center;
      font-size: 2em;
      color: #f5e146;
      text-shadow: 0 0 10px #f5e14680;
    }

    #searchForm {
      text-align: center;
      margin-bottom: 20px;
    }

    #searchInput {
      width: 80%;
      max-width: 400px;
      padding: 10px;
      border: 2px solid #8a2be2;
      border-radius: 8px;
      background-color: #1c1c1c;
      color: #fff;
      outline: none;
    }

    #searchInput::placeholder {
      color: #b19cd9;
    }

    button[type="submit"] {
      padding: 10px 16px;
      margin-left: 10px;
      border: none;
      border-radius: 8px;
      background: #f5e146;
      color: #000;
      font-weight: bold;
      cursor: pointer;
      box-shadow: 0 0 10px #f5e14660;
    }

    button[type="submit"]:hover {
      background: #fffa80;
    }

    #results {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 16px;
    }

    .card {
      background: #1c1c1c;
      border: 1px solid #8a2be2;
      border-radius: 8px;
      padding: 10px;
      box-shadow: 0 0 10px #8a2be260;
      transition: transform 0.2s ease-in-out;
    }

    .card:hover {
      transform: translateY(-4px);
      box-shadow: 0 0 20px #8a2be2a0;
    }

    .card img {
      width: 100%;
      height: auto;
      border-radius: 4px;
    }

    .card h3 {
      font-size: 1em;
      margin: 8px 0;
      color: #f0eaff;
    }

    .buttons {
      display: flex;
      gap: 8px;
      margin-top: 8px;
    }

    .buttons a {
      flex: 1;
      text-align: center;
      text-decoration: none;
      padding: 6px;
      background: #f5e146;
      color: #000;
      border-radius: 4px;
      font-size: 0.9em;
      font-weight: bold;
      box-shadow: 0 0 6px #f5e14680;
    }

    .buttons a:hover {
      background: #fff76a;
    }

    #loadMore {
      display: block;
      margin: 30px auto;
      padding: 10px 20px;
      background: #8a2be2;
      color: #fff;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: bold;
      box-shadow: 0 0 10px #8a2be280;
    }

    #loadMore[disabled] {
      background: #444;
      cursor: default;
      box-shadow: none;
    }

    #message {
      text-align: center;
      margin-top: 20px;
      color: #ff4f4f;
    }
  </style>
</head>
<body>
  <h1>Your Search</h1>
  <form id="searchForm">
    <input type="text" id="searchInput" placeholder="Enter search query" required>
    <button type="submit">Search</button>
  </form>

  <div id="results"></div>
<div id="sentinel" style="height: 20px;"></div>
<div id="loading" style="display: none; text-align: center; margin: 20px 0; color: #f5e146;">
    Loading more videos...
</div>
<div id="message"></div>

  <script src="/client/main.js"></script>
</body>
</html>
