Redirect Plugin
===============

`Redirect` ist ein [Herbie](http://github.com/getherbie/herbie) Plugin, mit dem du auf einer beliebigen Seite eine
Weiterleitung zu einer URL erreichen kannst. 

## Installation

Das Plugin installierst du via Composer.

	$ composer require getherbie/plugin-redirect

Danach aktivierst du das Plugin in der Konfigurationsdatei.

    plugins:
        enable:
            - redirect
        
Die Weitereleitung definierst du über die Seiteneigenschaften. Im einfachsten Fall sieht das so aus: 
        
    ---
    title: Weiterleitung
    redirect: http://www.getherbie.org
    ---

Per Default wird der Status 301 gesendet. Möchtest Du einen eigenen Status senden, kannst du das
wie folgt machen:
 
    ---
    title: Weiterleitung
    redirect:
      url: http://www.getherbie.org
      status: 302
    ---
 
 