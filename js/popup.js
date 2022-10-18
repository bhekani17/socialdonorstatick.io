window.addEventListener("load", function(){
    setTimeout(
        function open(event){
            document.querySelector(".popup").style.display = "block";
        },
        1000
    )
});

let closeBnt =  document.querySelector("#close");

closeBnt.addEventListener('click',function (){
   document.querySelector(".popup").style.display = "none";
    console.log("you clicked me")
})

function closeBox() {
    document.querySelector(".popup").style.display = "none";
}

