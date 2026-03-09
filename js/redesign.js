(function () {
  function byId(id) {
    return document.getElementById(id);
  }

  function fetchJson(url) {
    return fetch(url, { credentials: "same-origin" }).then(function (response) {
      if (!response.ok) {
        throw new Error("Request failed");
      }
      return response.json();
    });
  }

  var navToggle = document.querySelector(".nav-toggle");
  var nav = document.querySelector(".site-nav");

  if (navToggle && nav) {
    navToggle.addEventListener("click", function () {
      nav.classList.toggle("open");
      var expanded = navToggle.getAttribute("aria-expanded") === "true";
      navToggle.setAttribute("aria-expanded", String(!expanded));
    });
  }

  var current = (window.location.pathname.split("/").pop() || "index.html").toLowerCase();
  document.querySelectorAll(".site-nav a").forEach(function (link) {
    var href = (link.getAttribute("href") || "").toLowerCase();
    if (href === current) {
      link.classList.add("active");
    }
  });

  fetchJson("php/auth_status.php")
    .then(function (auth) {
      var donorOnlyPages = {
        "public dashboard.html": true,
        "donors profile.html": true
      };
      var adminOnlyPages = {
        "super admini dashboard.html": true,
        "administration profile.html": true
      };

      if (donorOnlyPages[current] && !auth.donor_logged_in) {
        window.location.href = "donors login.html?error=login_required";
        return;
      }

      if (adminOnlyPages[current] && !auth.admin_logged_in) {
        window.location.href = "login admin.html?error=login_required";
        return;
      }

      if (current === "donors profile.html" && auth.donor_logged_in) {
        fetchJson("php/donor_profile_get.php").then(function (profile) {
          var username = byId("username");
          var name = byId("name");
          var surname = byId("surname");
          var email = byId("email");
          var relationship = byId("relationship");
          var contact = byId("contact");

          if (username) username.value = profile.username || "";
          if (name) name.value = profile.first_name || "";
          if (surname) surname.value = profile.surname || "";
          if (email) email.value = profile.email || "";
          if (relationship) relationship.value = profile.emergency_contact || "";
          if (contact) contact.value = profile.emergency_number || "";
        }).catch(function () {
          // keep static fallback values
        });
      }

      if (current === "administration profile.html" && auth.admin_logged_in) {
        fetchJson("php/admin_profile_get.php").then(function (profile) {
          var adminUser = byId("adminUser");
          var adminName = byId("adminName");
          var adminSurname = byId("adminSurname");
          var adminEmail = byId("adminEmail");
          var role = byId("role");
          var phone = byId("phone");
          var permissions = byId("permissions");

          if (adminUser) adminUser.value = profile.username || "";
          if (adminName) adminName.value = profile.full_name || "";
          if (adminSurname) adminSurname.value = profile.surname || "";
          if (adminEmail) adminEmail.value = profile.email || "";
          if (role) role.value = profile.role || "";
          if (phone) phone.value = profile.cell_number || "";
          if (permissions) permissions.value = profile.permissions || "";
        }).catch(function () {
          // keep static fallback values
        });
      }
    })
    .catch(function () {
      // if PHP backend is unavailable, keep frontend behavior
    });

  fetchJson("php/dashboard_data.php")
    .then(function (data) {
      var statRequests = byId("statRequests");
      var statReceived = byId("statReceived");
      var statStock = byId("statStock");
      var adminVisitors = byId("adminVisitors");
      var adminNewDonors = byId("adminNewDonors");
      var adminNewRequests = byId("adminNewRequests");
      var recentDonorsList = byId("recentDonorsList");

      if (statRequests) statRequests.textContent = String(data.requests || 0);
      if (statReceived) statReceived.textContent = String(data.received || 0);
      if (statStock) statStock.textContent = String(data.stock || 0);
      if (adminVisitors) adminVisitors.textContent = String(data.visitors || 0);
      if (adminNewDonors) adminNewDonors.textContent = String(data.new_donors || 0);
      if (adminNewRequests) adminNewRequests.textContent = String(data.new_requests || 0);

      if (recentDonorsList && Array.isArray(data.recent_donors)) {
        recentDonorsList.innerHTML = "";

        if (data.recent_donors.length === 0) {
          var empty = document.createElement("li");
          empty.textContent = "No donor records yet.";
          recentDonorsList.appendChild(empty);
        } else {
          data.recent_donors.forEach(function (item) {
            var li = document.createElement("li");
            li.textContent =
              (item.name || "Unknown") + " • " +
              (item.blood_type || "Unknown") + " • " +
              (item.location || "Unknown");
            recentDonorsList.appendChild(li);
          });
        }
      }
    })
    .catch(function () {
      // keep static dashboard values
    });

  var mapEl = byId("donationMap");
  var needsList = byId("needsList");

  if (mapEl && needsList) {
    var fallbackPlaces = [
      { name: "Charlotte Maxeke Hospital", area: "Johannesburg CBD", blood: "O-", urgency: "Critical", lat: -26.1884, lng: 28.0398 },
      { name: "Chris Hani Baragwanath", area: "Soweto", blood: "A+", urgency: "High", lat: -26.2564, lng: 27.9427 },
      { name: "Helen Joseph Hospital", area: "Auckland Park", blood: "B-", urgency: "High", lat: -26.1812, lng: 28.0104 },
      { name: "Tembisa Hospital", area: "Tembisa", blood: "AB-", urgency: "Critical", lat: -25.9943, lng: 28.2228 },
      { name: "Rahima Moosa Hospital", area: "Coronationville", blood: "O+", urgency: "Moderate", lat: -26.1699, lng: 27.9964 }
    ];

    var renderMap = function (places) {
      if (!window.L) {
        needsList.innerHTML = "<li class='muted'>Live map failed to load. Please check your internet connection.</li>";
        return;
      }

      var map = L.map(mapEl).setView([-26.2041, 28.0473], 10);
      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap contributors"
      }).addTo(map);

      var bounds = L.latLngBounds([]);
      places.forEach(function (place) {
        var markerColor = place.urgency === "Critical" ? "#b81f24" : place.urgency === "High" ? "#f59e0b" : "#16803d";
        var lat = Number(place.lat);
        var lng = Number(place.lng);

        var marker = L.circleMarker([lat, lng], {
          radius: 8,
          color: markerColor,
          fillColor: markerColor,
          fillOpacity: 0.88,
          weight: 2
        }).addTo(map);

        marker.bindPopup(
          "<strong>" + place.name + "</strong><br>" +
          place.area + "<br>" +
          "Needed: " + place.blood + " (" + place.urgency + ")"
        );

        bounds.extend([lat, lng]);

        var listItem = document.createElement("li");
        var button = document.createElement("button");
        button.type = "button";
        button.className = "need-item";
        button.innerHTML = "<strong>" + place.name + "</strong><span>" +
          place.area + " | " + place.blood + " | " + place.urgency + "</span>";
        button.addEventListener("click", function () {
          map.flyTo([lat, lng], 13, { duration: 0.7 });
          marker.openPopup();
        });
        listItem.appendChild(button);
        needsList.appendChild(listItem);
      });

      if (bounds.isValid()) {
        map.fitBounds(bounds.pad(0.22));
      }
    };

    fetchJson("php/urgent_places.php")
      .then(function (rows) {
        if (!Array.isArray(rows) || rows.length === 0) {
          renderMap(fallbackPlaces);
          return;
        }

        var normalized = rows
          .map(function (row) {
            return {
              name: row.name,
              area: row.area,
              blood: row.blood,
              urgency: row.urgency,
              lat: row.lat,
              lng: row.lng
            };
          })
          .filter(function (place) {
            return place.name && place.area && place.blood && place.urgency;
          });

        renderMap(normalized.length ? normalized : fallbackPlaces);
      })
      .catch(function () {
        renderMap(fallbackPlaces);
      });
  }
})();
