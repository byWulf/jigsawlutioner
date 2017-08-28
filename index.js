const port = process.env.PORT || 1100;

const express = require('express');
const app = express();
const http = require('http').Server(app);
const io = require('socket.io')(http);
const Siofu = require('socketio-file-upload');
const MongoClient = require('mongodb').MongoClient;
const sharp = require('sharp');

const Jigsawlutioner = require('./src/jigsawlutioner');
const Debug = require('./src/debug');
const BorderFinder = require('./src/borderFinder');

app.use(express.static('client'));
app.use('/images', express.static('images'));

app.use(Siofu.router);
app.use('/jquery', express.static('node_modules/jquery/dist'));
app.use('/bootstrap', express.static('node_modules/bootstrap/dist'));
app.use('/fontawesome', express.static('node_modules/font-awesome'));
app.use('/tether', express.static('node_modules/tether/dist'));
app.use('/paper', express.static('node_modules/paper/dist'));
app.use('/popper', express.static('node_modules/popper.js/dist/umd'));

MongoClient.connect('mongodb://localhost:27017/jigsawlutioner').then((db) => {
    let collection = db.collection('sets');

    http.listen(port, () => {
        console.log('Server started on port ' + port);
    });

    let pendingPiece = null;
    let pieces = [];
    let groups = [];

    io.on('connection', (socket) => {
        console.log('user connected');

        let pieceIndices = [];
        for (let i = 0; i < pieces.length; i++) {
            pieceIndices.push({
                pieceIndex: pieces[i].pieceIndex,
                filename: pieces[i].files.original
            });
        }
        socket.emit('pieces', pieceIndices);

        const uploader = new Siofu();
        uploader.dir = __dirname + '/images';
        uploader.listen(socket);

        uploader.on('start', (event) => {
            Debug.startTime('1_uploading');

            console.log("upload started");
            io.sockets.emit('state', 'UPLOADING');
        });

        uploader.on('saved', (event) => {
            Debug.endTime('1_uploading');
            Debug.startTime('2_preprocessing');

            console.log("uploading finished, starting preprocessing", event.file.pathName);
            io.sockets.emit('state', 'PREPROCESSING');

            let image = sharp(event.file.pathName);
            image.metadata().then((data) => {
                return image.extract({
                    left: Math.round(data.width * 0.25),
                    top: Math.round(data.height * 0.25),
                    width: Math.round(data.width * 0.5),
                    height: Math.round(data.height * 0.5)
                }).png().toFile(event.file.pathName + '.resized.png');
            }).then(() => {
                return BorderFinder.findPieceBorder(event.file.pathName + '.resized.png');
            }).then((borderData) => {
                Debug.endTime('2_preprocessing');
                Debug.startTime('3_parsing');

                console.log("preprocessing finished, starting parsing");
                io.sockets.emit('state', 'PARSING', borderData);

                Jigsawlutioner.analyzeBorders(borderData.path).then((piece) => {
                    Debug.endTime('3_parsing');

                    let data = {
                        pieceIndex: piece.pieceIndex,
                        sides: piece.sides,
                        diffs: piece.diffs,
                        boundingBox: borderData.boundingBox,
                        dimensions: borderData.dimensions,
                        files: borderData.files
                    };
                    pieces.push(data);

                    io.sockets.emit('newPiece', {pieceIndex: data.pieceIndex, filename: data.files.original});

                    pendingPiece = piece;

                    console.log("parsing finished, asking if correct");
                    io.sockets.emit('state', 'CHECKPARSE', data);

                    Debug.output();
                }).catch((err) => {
                    console.log(err);
                    io.sockets.emit('state', 'ERROR', {atStep: 'Parsing', message: err});
                });
            }).catch((err) => {
                console.log('Error at preprocessing', err);
                io.sockets.emit('state', 'ERROR', {atStep: 'Preprocessing', message: err});
            });
        });

        uploader.on('error', (event) => {
            console.log('Error at uploading', event.message);
            io.sockets.emit('state', 'ERROR', {atStep: 'Uploading', message: event.message});
        });

        socket.on('group', (targetGroup) => {
            if (typeof groups[targetGroup] === 'undefined') {
                groups[targetGroup] = [];
            }

            groups[targetGroup].push(pieces[pieces.length - 1].pieceIndex);

            io.sockets.emit('state', 'UPLOAD');
        });

        socket.on('getPiece', (pieceIndex) => {
            console.log("piece " + pieceIndex + " requested");
            for (let i = 0; i < pieces.length; i++) {
                if (pieces[i].pieceIndex === parseInt(pieceIndex, 10)) {
                    socket.emit('piece', pieces[i]);
                }
            }
        });

        socket.on('comparePieces', (sourcePieceIndex, comparePieceIndex) => {
            let sourcePiece = null;
            for (let i = 0; i < pieces.length; i++) {
                if (pieces[i].pieceIndex === parseInt(sourcePieceIndex, 10)) {
                    sourcePiece = pieces[i];
                    break;
                }
            }
            let comparePiece = null;
            for (let i = 0; i < pieces.length; i++) {
                if (pieces[i].pieceIndex === parseInt(comparePieceIndex, 10)) {
                    comparePiece = pieces[i];
                    break;
                }
            }

            let results = {};
            if (sourcePiece && comparePiece) {
                for (let sourceSideIndex = 0; sourceSideIndex < sourcePiece.sides.length; sourceSideIndex++) {
                    for (let compareSideIndex = 0; compareSideIndex < comparePiece.sides.length; compareSideIndex++) {
                        results[sourceSideIndex + '_' + compareSideIndex] = Jigsawlutioner.getSideMatchingFactor(sourcePiece.sides[sourceSideIndex], comparePiece.sides[compareSideIndex], 0, 0);
                    }
                }
            }

            socket.emit('comparison', sourcePiece, comparePiece, results);
        });

        socket.on('findMatchingPieces', (sourcePieceIndex) => {
            let sourcePiece = null;
            for (let i = 0; i < pieces.length; i++) {
                if (pieces[i].pieceIndex === parseInt(sourcePieceIndex, 10)) {
                    sourcePiece = pieces[i];
                    break;
                }
            }

            let matches = Jigsawlutioner.findMatchingPieces(sourcePiece, pieces);

            socket.emit('matchingPieces', sourcePieceIndex, matches);
        });
    });
}).catch((err) => {
    console.log("Could not connect to mongoDB: ", err);
});


