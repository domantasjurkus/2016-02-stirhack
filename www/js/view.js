var draw_data = [];

function query(apihost, apiname) {

    $.ajax({
        url: apihost+"/get/"+apiname
    }).done(function(rawData) {

        var data = JSON.parse(rawData);

        addFirstLast(data, "204");
        addFirstLast(data, "404");
        addFirstLast(data, "500");

        drawTimeline();
    });

}

function addFirstLast(data, errorCode) {
    var first = timeConverter(data[errorCode]["times"].shift());
    var last  = timeConverter(data[errorCode]["times"].pop());

    draw_data.push({
        name: "Error "+ errorCode,
        date: first
    });

    draw_data.push({
        name: "Error "+ errorCode,
        date: last
    });
}

function timeConverter(UNIX_timestamp){
    var a = new Date(UNIX_timestamp * 1000);
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var year = a.getFullYear();
    var month = (a.getMonth()+1).toString().length == 2 ? a.getMonth()+1 : "0"+(a.getMonth()+1).toString();
    var date = a.getDate();
    var hour = a.getHours().toString().length == 2 ? a.getHours() : "0"+a.getHours().toString();
    var min = a.getMinutes().toString().length == 2 ? a.getMinutes() : "0"+a.getMinutes().toString();
    var sec = a.getSeconds().toString().length == 2 ? a.getSeconds() : "0"+a.getSeconds().toString();
    var time = year + '-' + month + '-' + date + 'T' + hour + ':' + min + ':' + sec ;
    return time;
}

function drawTimeline() {

    TimeKnots.draw("#timeline", draw_data, {
        dateFormat: "%m/%d %H:%M:%S",
        color: "#d55",
        width: 500,
        showLabels: true,
        labelFormat: "%A %H:%M"
    });




}