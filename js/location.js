let messegePop = document.querySelector(".messege");
let myLoaction = document.querySelector(".location");
let myDateTime = document.querySelector(".time_date");


   
const d = new Date();
let hour = d.getHours();
console.log(hour);

let mydata =  new Date().toLocaleString();
console.log(mydata)


fetch('https://api.ipregistry.co/?key=motsheejucfbs9sn')
     .then(function (response) {
        return response.json();
    })
    .then(function (payload) {
        let myCity = payload.location.country.name + ', ' + payload.location.city;
        myLoaction.innerHTML = myCity;
    });



    let msg ="Good Morning"

    if(hour >= 0 && hour <= 25){
         msg = "Good Afternoon"
        messegePop.innerHTML = msg;
        myDateTime.innerHTML = mydata;
        
       
    }else{
        
        (hour >= 00 && hour <= 06)
        msg = "Good Evening"
        messegePop.innerHTML = msg;
        myDateTime.innerHTML = mydata;
    }