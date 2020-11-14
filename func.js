

function createRequestObject() {
    var objeto;
    if (navigator.appName == "Microsoft Internet Explorer") {
        objeto = new ActiveXObject("Microsoft.XMLHTTP");
    }
    else {
        objeto = new XMLHttpRequest();
    }
    return objeto;
}
 

function sendRequest2() {
    var http2 = createRequestObject();
    http2.open("GET", "index.php?buscar=2", true);
    http2.onreadystatechange = function () { handleResponse2(http2); };
    http2.send();
}


function handleResponse2(http2) {
    var response2;   
    if (http2.readyState == 4) {
        response2 = http2.responseText;
        
        if (response2.substr(response2.length - 2) == "--") {
            window.location.href = "index.php";
        } else {
            document.getElementById("procesador").innerHTML =  response2;
            sendRequest2();
        }
        
    }
}


function Iniciar() {
    sendRequest2();    
}

