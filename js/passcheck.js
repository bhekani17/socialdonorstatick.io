let pwr = document.getElementById('password');
let lup = document.getElementById('lookup');
let mes = document.getElementById('message');

pwr .addEventListener('input' , () => {
    if(pwr.nodeValue.length > 0){
        mes.style.display = 'block' ;
    }else{
        mes.style.display = 'none';
    }
if(pwr.value.length < 4){
    lup.innerHTML = 'No, Your password is too weak';
    mes.style.color = '#ff4757';
}
else if(pwr.value.length >= 5 && pwr.value.length < 8){
    lup.innerHTML = 'mmh , Your password is too medium';
    mes.style.color = 'orange';
}
else if(pwr.value.length >= 10) {
    lup.innerHTML = 'mmh , Your password is too strong';
    mes.style.color = 'green';
}

})