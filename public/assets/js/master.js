class Lightbox{
    constructor(){
        this.init()
    }

    init(){
        this.container = document.createElement('div');
        this.container.id = 'lightbox';
        document.body.appendChild(this.container);

        this.lightboxImg = document.createElement('img');
        this.container.appendChild(this.lightboxImg);

        this.addListeners();
    }

    addListeners(){
        const images = document.querySelectorAll('.gallery img');
        images.forEach(img => {
            img.addEventListener('click', ()=> this.galleryImgClicked(img))
        })

        this.container.addEventListener('click', ()=>{
            this.hideLightbox()
        })

        document.addEventListener('keydown', (e) => {
            if(e.key === 'Escape') this.hideLightbox()
        })
    }

    hideLightbox(){
        this.container.classList.remove('active')
    }

    galleryImgClicked = (img) => {
        this.lightboxImg.src = img.src;
        this.container.classList.add('active')
    }
}

const lightbox = new Lightbox()

$('#imgPreview').hide();
$('#example-fileinput').change(function(){
    $('#imgPreview').show();
    const file = this.files[0];
    console.log(file);
    if (file){
    let reader = new FileReader();
    reader.onload = function(event){
        console.log(event.target.result);
        $('#imgPreview').attr('src', event.target.result);
    }
    reader.readAsDataURL(file);
    }
});
