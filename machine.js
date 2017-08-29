const port = process.env.PORT || 1100;

const express = require('express');
const app = express();
const http = require('http').Server(app);
const io = require('socket.io')(http);
const Siofu = require('socketio-file-upload');
const MongoClient = require('mongodb').MongoClient;
const sharp = require('sharp');
const { exec } = require('child_process');

const Jigsawlutioner = require('./src/jigsawlutioner');
const Debug = require('./src/debug');
const BorderFinder = require('./src/borderFinder');
const FileHelper = require('./src/fileHelper');

app.use(express.static('machineClient'));
app.use('/images', express.static('images'));

app.use(Siofu.router);
app.use('/jquery', express.static('node_modules/jquery/dist'));
app.use('/bootstrap', express.static('node_modules/bootstrap/dist'));
app.use('/fontawesome', express.static('node_modules/font-awesome'));
app.use('/tether', express.static('node_modules/tether/dist'));
app.use('/paper', express.static('node_modules/paper/dist'));
app.use('/popper', express.static('node_modules/popper.js/dist/umd'));
app.use('/animate.css', express.static('node_modules/animate.css'));
app.use('/bootstrap-notify', express.static('node_modules/bootstrap-notify'));

MongoClient.connect('mongodb://localhost:27017/jigsawlutioner').then((db) => {
    let collection = db.collection('sets');

    http.listen(port, () => {
        console.log('Server started on port ' + port);
    });

    let pieces = [];

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

        socket.on('takePicture', () => {

            FileHelper.getFreeFilename('/var/www/images/incoming.jpg').then((filename) => {
                let settings = [
                    '-ex off',
                    '-sh -100',
                    '-co 0',
                    '-br 50',
                    '-sa 50',
                    '-ISO 0',
                    '-awb fluorescent',
                    '-mm backlit',
                    '-roi 0.25,0.25,0.5,0.5',
                    '-drc high',
                    '-st',
                    '-q 50',
                    '-n',
                    '-t 1',
                    '-e jpg',
                    '-o ' + filename,
                    '-w 1000',
                    '-h 1000'
                ];

                console.log("Taking picture");
                Debug.startTime('1_takingpicture');
                exec('raspistill ' + settings.join(' '), (err, stdout, stderr) => {
                    if (err || stderr) {
                        console.log('Error at taking picture', err + stderr);
                        io.sockets.emit('state', 'ERROR', null, {atStep: 'TakingPicture', message: err.toString() + stderr});

                        return;
                    }

                    console.log("Took picture. Starting border recognition");
                    Debug.endTime('1_takingpicture');
                    Debug.startTime('2_preprocessing');
                    BorderFinder.findPieceBorder(filename, {debug: true, threshold: 225}).then((borderData) => {
                        Debug.endTime('2_preprocessing');
                        Debug.startTime('3_parsing');

                        console.log("Border found, starting parsing");
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

                            console.log("parsing finished");
                            io.sockets.emit('state', 'READY', data);

                            Debug.output();
                        }).catch((err) => {
                            console.log(err);
                            io.sockets.emit('state', 'ERROR', null, {atStep: 'Parsing', message: err.toString()});
                        });
                    }).catch((err) => {
                        console.log('Error at preprocessing', err);
                        io.sockets.emit('state', 'ERROR', null, {atStep: 'Preprocessing', message: err.toString()});
                    });
                });
            });
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


