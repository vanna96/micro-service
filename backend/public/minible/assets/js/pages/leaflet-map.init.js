var mapboxAccessToken = window.mapboxAccessToken || "";
var openStreetMapUrl = "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png";
var mapboxUrl = "https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token=" + encodeURIComponent(mapboxAccessToken);
var osmAttr = 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';
var mapboxAttr = 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery &copy; <a href="https://www.mapbox.com/">Mapbox</a>';

function tileLayerOptions(styleId) {
    return mapboxAccessToken
        ? { maxZoom: 18, attribution: mapboxAttr, id: styleId, tileSize: 512, zoomOffset: -1 }
        : { maxZoom: 18, attribution: osmAttr };
}

function tileLayer(styleId) {
    return L.tileLayer(mapboxAccessToken ? mapboxUrl : openStreetMapUrl, tileLayerOptions(styleId));
}

var mymap = L.map("leaflet-map").setView([51.505, -.09], 13);
tileLayer("mapbox/streets-v11").addTo(mymap);

var markermap = L.map("leaflet-map-marker").setView([51.505, -.09], 13);
tileLayer("mapbox/streets-v11").addTo(markermap);
L.marker([51.5, -.09]).addTo(markermap);
L.circle([51.508, -.11], { color: "#34c38f", fillColor: "#34c38f", fillOpacity: .5, radius: 500 }).addTo(markermap);
L.polygon([[51.509, -.08], [51.503, -.06], [51.51, -.047]], { color: "#5b73e8", fillColor: "#5b73e8" }).addTo(markermap);

var popupmap = L.map("leaflet-map-popup").setView([51.505, -.09], 13);
tileLayer("mapbox/streets-v11").addTo(popupmap);
L.marker([51.5, -.09]).addTo(popupmap).bindPopup("<b>Hello world!</b><br />I am a popup.").openPopup();
L.circle([51.508, -.11], 500, { color: "#f46a6a", fillColor: "#f46a6a", fillOpacity: .5 }).addTo(popupmap).bindPopup("I am a circle.");
L.polygon([[51.509, -.08], [51.503, -.06], [51.51, -.047]], { color: "#5b73e8", fillColor: "#5b73e8" }).addTo(popupmap).bindPopup("I am a polygon.");

var popup = L.popup();
var customiconsmap = L.map("leaflet-map-custom-icons").setView([51.5, -.09], 13);
L.tileLayer(openStreetMapUrl, { attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' }).addTo(customiconsmap);
var LeafIcon = L.Icon.extend({ options: { iconSize: [45, 95], iconAnchor: [22, 94], popupAnchor: [-3, -76] } });
var greenIcon = new LeafIcon({ iconUrl: "assets/images/logo-sm.png" });
L.marker([51.5, -.09], { icon: greenIcon }).addTo(customiconsmap);

var interactivemap = L.map("leaflet-map-interactive-map").setView([37.8, -96], 4);
function getColor(e) {
    return e > 1e3 ? "#497fe5" : e > 500 ? "#5b73e8" : e > 200 ? "#6d99eb" : e > 100 ? "#7fa5ed" : e > 50 ? "#91b2f0" : e > 20 ? "#a3bef2" : e > 10 ? "#b4cbf5" : "#bdd1f6";
}
function style(e) {
    return { weight: 2, opacity: 1, color: "white", dashArray: "3", fillOpacity: .7, fillColor: getColor(e.properties.density) };
}
tileLayer("mapbox/light-v9").addTo(interactivemap);
var geojson = L.geoJson(statesData, { style: style }).addTo(interactivemap);
var cities = L.layerGroup();
L.marker([39.61, -105.02]).bindPopup("This is Littleton, CO.").addTo(cities);
L.marker([39.74, -104.99]).bindPopup("This is Denver, CO.").addTo(cities);
L.marker([39.73, -104.8]).bindPopup("This is Aurora, CO.").addTo(cities);
L.marker([39.77, -105.23]).bindPopup("This is Golden, CO.").addTo(cities);

var grayscale = tileLayer("mapbox/light-v9");
var streets = tileLayer("mapbox/streets-v11");
var layergroupcontrolmap = L.map("leaflet-map-group-control", { center: [39.73, -104.99], zoom: 10, layers: [streets, cities] });
var baseLayers = { Grayscale: grayscale, Streets: streets };
var overlays = { Cities: cities };
L.control.layers(baseLayers, overlays).addTo(layergroupcontrolmap);
