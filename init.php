<?php


/**
 * An application instance (derived from \aneya\Core\Application) may be constructed at this point.
 * The framework will automatically store the application instance internally for future reference via the CMS::app() method.
 * If needed, you can replace the below constructor with your own class (derived from Application base class).
*/
// new Application();


/* ------ Add any custom includes and/or initialization code here ------*/
error_reporting (E_ALL);

// Example:
// CMS::loader()->addPath (new ClassLoaderPath('/app/classes/', '', 'class.'));

// Enable/disable debug mode
// CMS::app()->debugMode = true;