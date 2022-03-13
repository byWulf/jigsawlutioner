# jigsawlutioner.byWulf


## About
Jigsawlutioner is an algorithm designed to solve a jigsaw puzzle based on images taken by each puzzle piece.

![Transforming single pieces into solved puzzle](doc/solution_mockup.png)

## Why
Solving jigsaw puzzles by hand should be a relaxing leisure activity. But when it comes to a point, when you have a table full of black pieces, and all you can do it brutforcing them by trying each side to each other sides resolving in 4 solved connections within 2 hours, that is no fun anymore. This algorithm doesn't care about colors and only looks at the individual formed piece borders so it is able to attack exactly this problem case.

## How it works
It parses the border of a piece image and finds the four corners. Now you have four sides. These sides are then compared to all other sides of all other pieces and ranked by a matching probability. With this information, the solver tries to connect all pieces together as best as they fit. It is forgivable, meaning inperfect borders don't prevent a correct solution, because there can always be some shred from the cutting knifes. We are not in a perfect world with perfect borders, but that's not a problem.

## Solvable jigsaw puzzle manufacturers/forms
Currently only standard rectangle puzzles are supported by the algorithm. Although it is design to be able to solve all kind of rectangle puzzles, only Ravensburger puzzles are currently solved correctly. This is because of the very good and individual forms each side gets by Ravensburger. Other manufacturers use too similar or even identical side forms and therefore this algorithm is not suitable for them.

## Features
1. BorderFinder: Parses an image, extracts the border of the piece and returns an array of points describing the border
2. SideFinder: Analyzes the border points and determines, where each of the 4 corners is. Returns 4 arrays containing the border points of each side.
3. PieceAnalyzer: Normalizes the side points (rotate them horizontal, flatten them and divides them in 100 evently distributed points), generates classifiers of each side (is the nop inside or outside, is the nop more left or right, etc.) and returns a piece object.
4. SideMatcher: Returns the probability between two sides, how good they can fit together.
5. PuzzleSolver: Solves the jigsaw puzzle and returns a matrix of where every piece should be placed with correct rotation.


