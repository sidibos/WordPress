var config = module.exports;

config["CoreTests"] = {
	rootPath: '../',
    environment: "browser",
   /* extensions: [ require("buster-coverage") ],
    "buster-coverage": {
        outputDirectory: "build/logs/jscoverage", //Write to this directory instead of coverage
        format: "lcov", //At the moment cobertura and lcov are the only ones available
        combinedResultsOnly: true //Write one combined file instead of one for each browser
    },*/
    sources: [
        "static/javascript/assanka/common/common-v1.js",
        "static/javascript/assanka/storage/storage-v1.js",
        "static/javascript/assanka/deferred/deferred-v1.js",
        "static/javascript/assanka/list/list-v1.js",
        "static/javascript/assanka/interactionbridge/interactionbridge-v1.js",
        "static/javascript/assanka/eventmanager/eventmanager-v1.js",
    ],
    tests: [
        "_tests/static/javascript/assanka/deferred/deferred-v1Test.js",
        "_tests/static/javascript/assanka/list/list-v1Test.js",
        "_tests/static/javascript/assanka/interactionbridge/interactionbridge-v1Test.js",
        "_tests/static/javascript/assanka/storage/storage-v1Test.js",
        "_tests/static/javascript/assanka/eventmanager/eventmanager-v1Test.js"
    ]
};
