'use strict';

// Include Gulp & Tools We'll Use
var gulp      = require('gulp'),
  $           = require('gulp-load-plugins')(),
  del         = require('del'),
  runSequence = require('run-sequence'),

// Task configuration.
  theme         = __dirname + '/themes/ua_theme/',
  styleguide    = __dirname + '/styleguide/',

// Get theme sub-directories from Compass' config.rb.
  compass       = require('compass-options').dirs({'config': theme + 'config.rb'});


// Build styleguide.
gulp.task('styleguide', ['clean:styleguide', 'styleguide:markup'], $.shell.task([
    // kss-node [source folder of files to parse] [destination folder] --template [location of template files]
    'kss-node --config <%= config %>'
  ], {
    templateData: {
      config: theme + '/sass/style-guide/config.json'
    }
  }
));

gulp.task('styleguide:sass-colors', $.shell.task([
    'bundle exec sass --compass --scss --sourcemap=none --style expanded sass/style-guide/sass-colors.scss.txt sass/style-guide/sass-colors.hbs.txt'
  ], {cwd: theme}
));

gulp.task('styleguide:markup', ['styleguide:sass-colors'], $.shell.task([
    'head -n 2  sass/style-guide/sass-colors.hbs.txt | tail -n 1 > sass/style-guide/sass-colors.hbs'
  ], {cwd: theme}
));

// Lint JavaScript.
gulp.task('lint:js', $.shell.task([
    'eslint ' + theme
  ], {
    cwd: __dirname,
    ignoreErrors: true
  }
));

// Lint Sass.
gulp.task('lint:sass', function() {
  return gulp.src(theme + compass.sass + '/**/*.scss')
    .pipe($.scssLint({'bundleExec': true}));
});

// Lint Sass and JavaScript.
gulp.task('lint', function (cb) {
  runSequence(['lint:js', 'lint:sass'], cb);
});

// Build CSS.
gulp.task('styles', ['clean:css'], $.shell.task([
    'bundle exec compass compile --time --sourcemap --output-style expanded'
  ], {cwd: theme}
));

gulp.task('bundle', $.shell.task([
    'bundle'
  ]
));

gulp.task('styles:production', ['clean:css'], $.shell.task([
    'bundle exec compass compile --time --no-sourcemap --output-style expanded'
//    'bundle exec compass compile --time --no-sourcemap --output-style compressed'
  ], {cwd: theme}
));

// Watch for front-end changes and rebuild on the fly.
gulp.task('watch', ['clean:css', 'watch:css', 'watch:js'],
  // This task cannot be used in a dependency, since this task won't ever end
  // due to "compass watch" never completing.
  $.shell.task(
    ['bundle exec compass watch --time --sourcemap --output-style expanded'],
    {cwd: theme}
  )
);
gulp.task('watch:css', ['styleguide', 'lint:sass'], function() {
  return gulp.watch(theme + compass.sass + '/**/*.scss', ['styleguide', 'lint:sass']);
});
gulp.task('watch:js', ['lint:js'], function() {
  return gulp.watch(theme + compass.js + '/**/*.js', ['lint:js']);
});

// Clean styleguide directory.
gulp.task('clean:styleguide', del.bind(null, [styleguide + '*.html', styleguide + 'public'], {force: true}));

// Clean CSS directory.
gulp.task('clean:css', del.bind(null, [theme + '**/.sass-cache', theme + compass.css + '/**/*.css', theme + compass.css + '/**/*.map'], {force: true}));

// Clean all directories.
gulp.task('clean', ['clean:css', 'clean:styleguide']);

// Production build of front-end.
gulp.task('build', ['bundle', 'styles:production', 'styleguide'], function (cb) {
  // Run linting last, otherwise its output gets lost.
  runSequence(['lint'], cb);
});

// The default task.
gulp.task('default', ['build']);


// Resources used to create this gulpfile.js:
// - https://github.com/google/web-starter-kit/blob/master/gulpfile.js
// - https://github.com/north/generator-north/blob/master/app/templates/Gulpfile.js
