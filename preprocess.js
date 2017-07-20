const Jimp = require('jimp');

function preprocess(file) {
    console.log("start", file);
    Jimp.read(file).then((image) => {
        console.log("loaded", file)
        image.invert()
            .write(file);
    }).catch((err) => {
        console.log(file, err);
    });
}

    preprocess(__dirname + '/images/IMG_2592-1-0_preprocessed.jpg');