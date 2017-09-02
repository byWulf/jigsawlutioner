const port = process.env.PORT || 1100;

const express = require('express');
const app = express();
const http = require('http').Server(app);
const io = require('socket.io')(http);
const Siofu = require('socketio-file-upload');
const MongoClient = require('mongodb').MongoClient;
const { exec } = require('child_process');
const rpio = require('rpio');
const path = require("path");
const sharp = require('sharp');

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


rpio.init({
    mapping: 'gpio'
});

let pins = {
    conveyor: 20,
    conveyorEnable: 26,
    sensor: 16
};

rpio.open(pins.conveyor, rpio.OUTPUT, rpio.LOW);
rpio.open(pins.conveyorEnable, rpio.OUTPUT, rpio.HIGH);
rpio.open(pins.sensor, rpio.INPUT, rpio.PULL_DOWN);

let state = null;
let mode = 'scan';
let lastStart = Date.now();
let conveyorRunning = false;
let stopAction = null;
function startConveyor() {
    if (!state) return;

    rpio.write(pins.conveyor, rpio.HIGH);
    conveyorRunning = true;
    lastStart = Date.now();
}

rpio.poll(pins.sensor, () => {
    if (conveyorRunning && (lastStart  === null || lastStart + 500 < Date.now())) {
        rpio.write(pins.conveyor, rpio.LOW);
        conveyorRunning = false;

        stopAction();
    }
});

process.stdin.resume();
function exitHandler(err) {
    rpio.close(pins.conveyor);
    rpio.close(pins.conveyorEnable);
    rpio.close(pins.sensor);

    if (err) console.log(err.stack);
    process.exit();
}
process.on('exit', exitHandler);
process.on('SIGINT', exitHandler);
process.on('uncaughtException', exitHandler);

function setState(newState) {
    state = newState;
    io.sockets.emit('machineState', state);
}

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
        socket.emit('mode', mode);
        socket.emit('machineState', state);
        socket.emit('pieces', pieceIndices);

        socket.on('mode', (newMode) => {
            if (['scan', 'compare'].indexOf(newMode) > -1) {
                mode = newMode;
                io.sockets.emit('mode', mode);
            }
        });

        socket.on('startMachine', () => {
            if (!state) {
                stopAction = () => {
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
                            '-drc high',
                            '-st',
                            '-q 50',
                            '-n',
                            '-t 1',
                            '-e jpg',
                            '-o ' + filename,
                        ];

                        console.log("Taking picture");
                        Debug.startTime('1_takingpicture');
                        exec('raspistill ' + settings.join(' '), (err, stdout, stderr) => {
                            if (err || stderr) {
                                io.sockets.emit('message', 'error', {atStep: 'TakingPicture', message: err.toString() + stderr});
                                startConveyor();

                                return;
                            }

                            let borderData = null;

                            sharp(filename).extract({left: 923, top: 997, width: 1066, height: 1168}).resize(913, 1000).png().toFile(filename + '.preprocessed.png').then(() => {
                                console.log("Took picture (" + path.basename(filename) + ".preprocessed.png). Starting border recognition");
                                Debug.endTime('1_takingpicture');
                                Debug.startTime('2_preprocessing');

                                return BorderFinder.findPieceBorder(filename + '.preprocessed.png', {debug: true, threshold: 225});
                            }).then((borderResult) => {
                                borderData = borderResult;

                                Debug.endTime('2_preprocessing');
                                Debug.startTime('3_parsing');
                                console.log("Border found, starting parsing");

                                return Jigsawlutioner.analyzeBorders(borderData.path);

                            }).then((piece) => {
                                Debug.endTime('3_parsing');

                                if (mode === 'compare') {

                                } else {
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
                                }

                                console.log("parsing finished");
                                io.sockets.emit('message', 'success');

                                Debug.output();
                                startConveyor();
                            }).catch((err) => {
                                io.sockets.emit('message', 'error', {atStep: 'Processing', message: err.toString()});
                                startConveyor();
                            });
                        });
                    });
                };
                setState('running');
                startConveyor();
            }
        });

        socket.on('stopMachine', () => {
            if (state) {
                setState(null);
            }
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

        socket.on('getPlacements', () => {
            console.log("placements requested", Date.now());
            let placements = Jigsawlutioner.getPlacements(pieces);
            for (let groupIndex in placements) {
                if (!placements.hasOwnProperty(groupIndex)) continue;

                for (let x in placements[groupIndex]) {
                    if (!placements[groupIndex].hasOwnProperty(x)) continue;

                    for (let y in placements[groupIndex][x]) {
                        if (!placements[groupIndex][x].hasOwnProperty(y)) continue;

                        placements[groupIndex][x][y] = placements[groupIndex][x][y].pieceIndex;
                    }
                }
            }
            console.log("returning placements", Date.now());

            socket.emit('placements', placements);
        });
    });
}).catch((err) => {
    console.log("Could not connect to mongoDB: ", err);
});


