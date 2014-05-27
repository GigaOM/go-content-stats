/*global module:false*/

module.exports = function(grunt) {
	var sass_files = [
		'components/sass/**/*.scss'
	];

	// Project configuration.
	grunt.initConfig({
		compass: {
			prod: {
				config: 'config.rb',
				debugInfo: false
			}
		},
		watch: {
			files: sass_files,
			tasks: [
				'compass:prod'
			]
		}
	});

	// Default task.
	grunt.loadNpmTasks( 'grunt-contrib-compass' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.registerTask( 'default', ['compass:prod'] );
};
