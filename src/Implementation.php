<?php namespace Jenssegers\ImageHash;

interface Implementation
{
    public function hash(\GdImage $image): Hash;
}
