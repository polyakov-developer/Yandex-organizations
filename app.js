ymaps.ready(function() {
    initMap();
    createHandlers();
});

var myMap = "",
    apikey = "c81434a6-f278-4862-8e9a-d968fd82bd57", // поиск по организациям
    apikey2 = "e736a73e-b600-4157-ab1d-85e448329b46", // геоопределение
    myCollectionRectangle,
    myCollectionPoint,
    center = [],
    bbox = [],
    region = document.getElementById('region'),
    city = document.getElementById('city'),
    searchQuery = document.getElementById('query-string'),
    companyArr = {};

function selectRegion(value) {
    ymaps.borders.load('RU', {
        lang: 'ru',
        quality: 3
    }).then(function (result) {
        myMap.geoObjects.removeAll();

        var region = result.features.find(x => x.properties.iso3166 === value);
        
        var geoObject = new ymaps.GeoObject(region, {
            geodesic: true,
            fillColor: '#7df9ff33',
            fillOpacity: 0.7,
            strokeColor: "#209ce4",
            strokeWidth: 3,
        });

        myMap.geoObjects.add(geoObject);

        myMap.setBounds(geoObject.geometry._bounds);

        console.log("region: ", geoObject);
    }, {});
}

function selectCity(city) {
    var geoCity = ymaps.geocode(city);
    
    geoCity.then(function (res) {
        myCollectionRectangle.removeAll();

        var coordinates = res.geoObjects.properties._data.metaDataProperty.GeocoderResponseMetaData.boundedBy;

        var rectangle = new ymaps.Rectangle([
            // Задаем координаты диагональных углов прямоугольника.
            coordinates[0].reverse(),
            coordinates[1].reverse()
        ], {}, {
            strokeColor: '#209ce4',
            strokeWidth: 3,
            fillOpacity: 0,
            borderRadius: 6,
            zIndex: 999
        });
        
        myCollectionRectangle.add(rectangle);

        myMap.geoObjects.add(myCollectionRectangle);
        myMap.setBounds(coordinates);

        bbox = coordinates.slice(0);

        /* развернуть координаты, потому что сервак яндекса 
           в функции getResultsOfRequest() не принимает координаты в нужном порядке */
        bbox.map(function(row){
            return row.reverse();
        });

        console.log("city: ", geoCity);
    }, {});
}

function getResultsOfRequest(queryString, _skip = 0) {
    let found = 0,
        maxResults = 5000,
        skip = _skip,
        allResults = [];
        
    $(function fetchData() {

        setTimeout(function() {
            $.getJSON("https://search-maps.yandex.ru/v1/",
                {
                    text: queryString,
                    lang: "ru_RU",
                    skip: skip,
                    results: maxResults,
                    bbox: bbox[0] + "~" + bbox[1],
                    apikey: apikey,
                },
                function (json) {

                    try {
                        allResults.push(json);
                        found = json.properties.ResponseMetaData.SearchResponse.found;

                        printObjects(allResults, found);

                        console.log("Y.Maps JSON: ", allResults);
                    } catch (error) {
                        console.log(error);
                    }
                    
                }
            );
        }, 2500);

    });
}

function printObjects(allResults, _found) {
    let tbody = document.getElementById("tbody"),
        found = document.getElementById("found");

    // Очистить строки
    tbody.innerHTML = "";
    
    // Найдено организаций
    found.innerHTML = _found;

    // Очистить все метки с карты
    myCollectionPoint.removeAll();

    for (let i = 0; i < allResults.length; i++) {

        let current_arr = allResults[i].features;

        for (let j = 0; j < current_arr.length; j++) {
            
            let row               = document.createElement("tr"),
                colNum            = document.createElement("td"),
                colName           = document.createElement("td"),
                colAddress        = document.createElement("td"),
                colTelephone      = document.createElement("td"),
                colSocialNetworks = document.createElement("td"),
                colWebsite        = document.createElement("td"),
                num, company, address, telephones = [], socialNetworks = [], website,
                curParamsArr = {};

            num     = current_arr[j].properties.id;
            company = current_arr[j].properties.name;
            address = current_arr[j].properties.description;

            colNum.classList.add("num");
            colNum.textContent = num;
            
            colName.classList.add("company");
            colName.textContent = company;
            
            colAddress.classList.add("address");
            colAddress.textContent = address;

            colTelephone.classList.add("telephones");
            colTelephone.textContent = "–";

            colSocialNetworks.classList.add("social-networks");
            colSocialNetworks.textContent = "–";

            colWebsite.classList.add("website");
            colWebsite.textContent = "–";

            try {
                // Социальные сети
                if (current_arr[j].properties.CompanyMetaData.hasOwnProperty("Links")) {
                    colSocialNetworks.textContent = "";
                    
                    for (var k = 0; k < current_arr[j].properties.CompanyMetaData.Links.length; k++) {
                        let socialNetwork = current_arr[j].properties.CompanyMetaData.Links[k].href;
                        socialNetworks.push(socialNetwork);
                    }
                }

                // Сайт
                if (current_arr[j].properties.CompanyMetaData.hasOwnProperty("url")) {
                    website = current_arr[j].properties.CompanyMetaData.url;

                    colWebsite.textContent = "";

                    let value = document.createElement("a");
                    value.href = website;
                    value.target = "_blank";
                    value.textContent = website;
                    colWebsite.appendChild(value);
                }

                // Телефоны
                if (current_arr[j].properties.CompanyMetaData.hasOwnProperty("Phones")) {
                    colTelephone.textContent = "";

                    for (let k = 0; k < current_arr[j].properties.CompanyMetaData.Phones.length; k++) {
                        let telephone = current_arr[j].properties.CompanyMetaData.Phones[k].formatted;
                        telephones.push(telephone);
                    }
                }
            } catch (ex) {
                console.log("Error: ", ex);
            }

            curParamsArr.id      = num;
            curParamsArr.company = company;
            curParamsArr.address = address;
            curParamsArr.socialNetworks = socialNetworks;
            curParamsArr.telephones = telephones;
            curParamsArr.website = website;

            companyArr[j] = curParamsArr;

            var placemark = new ymaps.Placemark(current_arr[j].geometry.coordinates.reverse(), {
                balloonContentHeader: company,
                balloonContentBody: address,
                balloonContentFooter: website ? `<a href="` + website + `" target="_blank">` + website + `</a>` : null,
            });
            
            myCollectionPoint.add(placemark);

            
            telephones.forEach(item => {
                let value = document.createElement("span");
                value.textContent = item;

                colTelephone.append(value) + ";";
            });

            socialNetworks.forEach(item => {
                let value = document.createElement("a");
                
                value.href = item;
                value.target = "_blank";
                value.textContent = item + ";";

                colSocialNetworks.appendChild(value);
            });

            row.appendChild(colNum);
            row.appendChild(colName);
            row.appendChild(colAddress);
            row.appendChild(colTelephone);
            row.appendChild(colSocialNetworks);
            row.appendChild(colWebsite);

            tbody.appendChild(row);
        }
    }

    myMap.geoObjects.add(myCollectionPoint);

    setTimeout(function() {
        document.getElementById("loader").style.display = "none";
        
        $("#export-to-excel").show();
    }, 1000);
}

// ===========

function createHandlers() {
    region.addEventListener("change", function(event) {
        selectRegion(event.target.value);
        
        city.value = "";
    });
    
    city.addEventListener("keyup", function(event) {
        if (event.keyCode === 13) {
            
            if (city.value == "") {
                return false;
            }

            selectCity(event.target.value);
        }
    });

    city.addEventListener("focusout", function(event) {
        selectCity(event.target.value);
    });
    
    searchQuery.addEventListener("keyup", function(event) {
        if (event.keyCode === 13) {

            if (city.value == "") {
                alert("Вы не заполнили поле «Город»");
                city.focus();
                return false;
            }
            
            if (searchQuery.value == "") {
                alert("Вы не заполнили поле «Организация»");
                return false;
            }

            document.getElementById("loader").style.display = "block";

            getResultsOfRequest(event.target.value, 0);
        }
    });
}

function localeRegion(center) {
    center = center.reverse();
    coordinates = center.join(",");

    console.log(coordinates);

    $.getJSON("https://geocode-maps.yandex.ru/1.x/?apikey=" + apikey2 + "&format=json&geocode=" + coordinates + "&kind=locality&results=1", function(json) {
        console.log(json);
    });
}

function initMap() {
    ymaps.geolocation.get({
        provider: 'yandex',
        mapStateAutoApply: true
    }).then(function (result) {
        center = result.geoObjects.position;

        myMap = new ymaps.Map("map", {
            center: center,
            zoom: 6
        }, {
            searchControlProvider: 'yandex#search'
        });

        myCollectionRectangle = new ymaps.GeoObjectCollection();
        myCollectionPoint = new ymaps.GeoObjectCollection();
    });
}

function export2PHP() {
    $.ajax({
        url: "export.php",
        method: "POST",
        data: companyArr,
        success: function(data){
            console.log(data);;
        },
        error: function(data){
            console.log(data);
        }
    });

    console.log(companyArr);
}