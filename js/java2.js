const vdieo =Document.querySelector(".video")
const cameraButton =document.querySelector(".camera")
const canvas = document.querySelector(".canvas")
navigator.mediaDevices.getUserMedia({video : true})
.then(stream => {
    video.srcObject = stream;
    video.play(1)
})