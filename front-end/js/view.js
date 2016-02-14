

function query(apihost, apiname, from, to) {
    var draw_data = [];
    $.ajax({
        url: apihost+"/get/"+apiname+"/"+from+"/"+to
    }).done(function(rawData) {
        console.log(apihost+"/get/"+apiname+"/"+from+"/"+to);
        var data = JSON.parse(rawData);
        console.log(data);
        if (data["204"]) addData(draw_data, data, "204");
        if (data["404"]) addData(draw_data, data, "404");
        if (data["500"]) addData(draw_data, data, "500");

        drawTimeline(draw_data);
    });

}

function addData(draw_data, data, errorCode) {
    data[errorCode].times.forEach(function(o){
        draw_data.push({
            name: "Error "+ errorCode,
            date: timeConverter(o)
        });
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

function drawTimeline(draw_data) {
    console.log("hello from timeline");
    $('#timeline').remove();
    $(".abc").append("<div id='timeline' style='margin-left:100px'></div>");
    TimeKnots.draw("#timeline", draw_data, {
        dateFormat: "%m/%d %H:%M:%S",
        color: "#d55",
        width: 800,
        showLabels: true,
        labelFormat: "%A %H:%M"
    });




}