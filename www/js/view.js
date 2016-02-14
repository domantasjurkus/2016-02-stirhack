

var nonDatedata = [
    {"value": 224, "name": "Player 1" },
    {"value": 249, "name": "Player 2" },
    {"value": 297, "name": "Player 3" },
    {"value": 388, "name": "Player 4" },
    {"value": 397, "name": "Player 5" },
    {"value": 418, "name": "Player 6" }
];
TimeKnots.draw("#timelineNonDate", nonDatedata, {dateDimension:false, color: "teal", width:500, showLabels: true, labelFormat: "%Y"});



var kurbickFilms = [
    {name:"Day of the Fight",             date: "1951-04-26", img: "http://upload.wikimedia.org/wikipedia/en/thumb/c/c4/Day_of_the_Fight_title.jpg/215px-Day_of_the_Fight_title.jpg"},
    {name:"The Seafarers", 	            date: "1953-10-15", img: "http://upload.wikimedia.org/wikipedia/en/thumb/6/6c/Seafarers_title.jpg/225px-Seafarers_title.jpg"},
    {name:"Lolita (1962 film)", 	        date: "1962-06-13", img: "http://upload.wikimedia.org/wikipedia/en/thumb/7/72/LolitaPoster.jpg/215px-LolitaPoster.jpg"},
    {name:"Fear and Desire",              date: "1953-03-31", img: "http://upload.wikimedia.org/wikipedia/en/f/f7/Fear_and_Desire_Poster.jpg"},
    {name:"Paths of Glory",               date: "1957-12-25", img: "http://upload.wikimedia.org/wikipedia/en/thumb/b/bc/PathsOfGloryPoster.jpg/220px-PathsOfGloryPoster.jpg"},
    {name:"A Clockwork Orange (film)",    date: "1971-12-19", img: "http://upload.wikimedia.org/wikipedia/en/thumb/4/48/Clockwork_orangeA.jpg/220px-Clockwork_orangeA.jpg"},
    {name:"Killer's Kiss",                date: "1955-09-28", img: "http://upload.wikimedia.org/wikipedia/en/thumb/a/a6/KillersKissPoster.jpg/220px-KillersKissPoster.jpg"}
];

TimeKnots.draw("#timeline1", kurbickFilms, {dateFormat: "%B %Y", color: "#696", width:500, showLabels: true, labelFormat: "%Y"});





/*var agenda = new Array();
 var day = 86400000;
 var today = new Date();
 var series = ["Serie1", "Serie 2", "Serie 3"];
 for(var i=-3; i<4; i++){
 var thisdate = new Date(today.getTime() + i*day).toUTCString();
 var thiscolor = "#44a";
 var thisname = "Free day";
 if(Math.random()>0.5){
 thiscolor = "#b00";
 thisname = "Meeting";
 }
 serieId = parseInt(Math.random()*100) %3;
 thisseries = series[serieId];
 var thiswidth = (i<0)?1:5;
 agenda.push({date: thisdate, name: thisname,  series: thisseries })
 }
 TimeKnots.draw("#timeline3", agenda, {dateFormat: "%A", radius: 20});*/