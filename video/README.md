Herbie Video Plugin
=====================

`Video` ist ein [Herbie](http://github.com/getherbie/herbie) Plugin, mit dem du Videos von 
[YouTube](http://www.youtube.com) und [Vimeo](https://vimeo.com) in deine Website einbettest.


Installation
-------------

Das Plugin installierst du via Composer.

	$ composer require getherbie/plugin-youtube

Danach aktivierst du das Plugin in der Konfigurationsdatei.

    plugins:
        enable:
            - video


Anwendung
---------

Nach der Installation stehen dir die Twig-Funktion `video_youtube` und `video_vimeo` zur Verfügung. Diese rufst du wie folgt auf:

    {{ video_youtube("_0TfPpjDkWU", 480, 320) }}
    {{ video_vimeo("30459532", 480, 320) }}

Alternativ kannst du die Funktion auch mit benannten Argumenten aufrufen.

    {{ video_youtube(id="_0TfPpjDkWU", width="480", height="320", responsive="1") }}
    {{ video_vimeo(id="30459532", width=480, height=320, responsive=1) }}


Parameter
---------

Name        | Beschreibung                          | Typ       | Default
:---------- | :------------------------------------ | :-------- | :------
id          | Die ID des Videos                     | string    |  
width       | Die Breite des Videos in Pixel        | int       | 480
height      | Die Höhe des Videos in Pixel          | int       | 320
responsive  | Definiert ob das Video responsiv ist  | bool      | true


Demo
----

<http://getherbie.org/blog/2014/05/09-responsive-youtube-videos>  
<http://getherbie.org/blog/2014/05/21-vimeo-responsive-videos>

