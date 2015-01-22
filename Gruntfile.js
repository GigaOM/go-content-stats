/*global module:false*/

module.exports = function(grunt) {
	'use strict';

	var sass_files = [
		'components/sass/**/*.scss'
	];
	var js_files = [
		'components/js/lib/**/*.js'
	];

	// Project configuration.
	grunt.initConfig({
		uglify: {
			compress: {
				files: [
					{
						expand: true, // enable dynamic expansion
						cwd: 'components/js/lib/', // src matches are relative to this path
						src: ['**/*.js'], // pattern to match
						dest: 'components/js/min/'
					}
				]
			}
		},
		compass: {
			prod: {
				config: 'config.rb',
				debugInfo: false
			}
		},
		watch: {
			files: js_files.concat( sass_files ),
			tasks: [
				'compass:prod',
				'newer:uglify'
			]
		}
	});

	// Default task.
	grunt.loadNpmTasks( 'grunt-newer' );
	grunt.loadNpmTasks( 'grunt-contrib-compass' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.registerTask( 'default', ['newer:uglify', 'compass:prod'] );
};