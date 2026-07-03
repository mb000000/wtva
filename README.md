# Introduction
## Background
The Wind Turbine Visualisation Aid idea came out of a discussion about how to 
actually "see" proposed onshore wind turbine developments, 
especially given the directions in which those developments are going - that is with
larger and more numerous turbines.
## Prior Art
Having established a "need", the next step was to consider how to best "solve" the
"problem". After some web research, it turned out someone else in Australia 
had the same ideas about visualisation and had taken the same 
approach, that is by generating a 3D model of a wind turbine in a KML file, available
for viewing in Google Earth.
## KML / KMZ Usage
Prior experience with KML files, showed that although they could certainly be used
for the requisite 3D models, they would be VERY inefficient when wanting what is
effectively multiple instances of the same model. This is because KML files are not
designed to handle large numbers of identical objects efficiently, and can lead to
performance issues and increased file size. Using inspection of the Australian
"example", where, in fact, a KMZ file had been used, it became apparent that their
solution was to use a Collada .DAE file to describe the 3D model and a KML file to 
"invoke" instances of that model in the required locations. In addition, they had the
sensible idea of providing multiple instances of the 3D model in different
orientations. This allowed for viewing from "anywhere", in such a way that would see
the turbines full face on, the most visually impactful orientation.
## Collada Modelling
So, now, wtva had a workable "solution", but how to generate the Collada .DAE file?
It turns out, one type of these (perhaps there are others) is to generate vertices
and normals and then link the two with defined triangles to create visible surfaces. 
Okay - so, how to model a wind turbine? In a simplified view, they're "just" a tower,
the nacelle at the top of the tower and then blades attached to an axle at the front 
of the nacelle. Great!
### Primitives 1 & 2
All the towers I've seen at cylindrical, albeit tapering at the top (making them
frusta - ha, a great word for Scrabble), so, that's the first primitive - a frustum.
The nacelles I've seen are variously cylindrical or rectangular, so primitive two 
is just a box.
### Primitive 3
So, the blades? Well, each one could be modelled as a box, a frustum or any number 
of other primitives. But to make the visualisation as convincing as possible, it 
seemed like they need to be tapering (like a frustum, yay!), but with a longitudinal
twist and have something like an aerodynamic cross section (an aerodynamics chord). 
Also, a longitudinal flattening. Hmmm - not so easy. But perhaps take a 
hemisphere, elongate it, flatten that, twist it and "somehow" create a cross section
using an elongation in one axis, but only applied for half of the cross section. So,
primative three - a sphere.
### Primitive 0
And the primitives have to be created such that curved surfaces are actually made up 
of many flat "segments". So a crude circle might be a hexagon or an octogon - 
the more vertices, the more like a "real" circle it becomes - actually 32 vertices
is pretty convincing.
If one conceptually extrudes that, one gets a cylinder. How to extrude? Well, just
make two cicles parallel to each other (with the same number of vertices) 
and then join the vertices both within each circle and between the circles. The
joins between the circles are rectangles (so just two triangles - remember the 
Collada description above?) - voila, a cylinder. And a frustum is just the same, 
with the top circle being smaller than the bottom circle (or vice versa)

Boxes are "trival" - 8 vertices with rectangles for the 6 faces (two triangles 
per face).

Spheres? Well, a simple approach is just to make lots of stacked frusta of
reducing dimensions, so, really, stacked circles reducing to zero radius.
### Primitive Refinements
There are some further refinements, such as wanting only part of a circle, e.g. 
a semicircle or quarter circle (or anything in between). That raises the question 
of how the missing section is closed - like a pie or "straight across" 
(a mathematical chord)? And then, by extension, the same for the cylinder / frustum
and sphere. That's algorithmic and not that complicated, but best worked out
on paper!
### Transformations
The Collada .DAE files use metric units, so the 3D locations are in metres. That
makes the 3D geometry relatively straight-forward to work with. Each 3D point 
can be transformed by translation, rotation and scaling. With those three transforms, 
almost all the modelling can be acheived. But not for the blade. So, 
in terms of implementation, I chose to use a queue of transform callbacks - 
each trasnform being successively applied to the 3D point. 

That meant for the blade I could implement something that handled the application 
of the scaling (in this case, the X axis) but only for positive values of X. So, 
if I arrange that a circle is flat (i.e. only varying in X and Y) and centred on
the origin (0,0), by scaling for positive values of X, I get a (potentially) 
infinite cylinder. But, by scaling for negative values of X, I get a (potentially) 
highly tapered semicircle "at the front", which is reasonably convincing as a 
wing chord. Then, by applying a rotation about Z (assuming the base frustum is 
"upright") for increasing values of Z, we get a longitudinal twist.

That was the 3D modelling solved (!). And thus the generation of the .DAE file.
## Collada DAE and KML?
The Collada 3D model is easily referred to by the KML file, which allows the one 
model to be instantiated multiple times, once at location. In addition, the 
instantiation also allows some simple transforms to be performed, so the 
different orientations are handled simply by applying a rotation in the Z axis
(ah - many uses of Collade DAE models decide to use a "Y axis is up" convention - so, 
in those instances, that would be a rotation about the Y axis).

The KMZ file is simply a ZIP file containing the KML file and the Collada DAE file - 
yes, really.
## Co-ordinate Locations
Next was the handling of the co-ordinate locations. In addition to Latitude and 
Longitude, being based in the UK means also accepting UK Ordnance Survey (OS) 
grid references. These are a pair of numbers, the first being the easting and 
the second being the northing. The OS grid references can either be solely in 
numbers or by using two letters followed by numbers - either way, the "challenge" 
is converting from this grid mechanism (which is a projection - remember Mercator 
projected? (as an aside - it's an interesting album by East of Eden)) to latitude 
and longitude. The maths for this are horrendous, but it's fairly well documented. 
Then after having converted from the OS Grid reference, there's also the need to 
change the datum from Airy1830 to WGS84. These data effectively define the 
approximation of the shape of the Earth, which is an ellipsoid. The different data 
use subtly different approximations, so the conversion is quite important. Again, 
this is well documented (and not quite as horrendous as the projection maths).
## Finally
With all that under the covers, the wtva is accessed (or accessible) via 
a web page which allows various parameters defining the wind turbines to be entered. 
And then the locations. The parameters are sense checked in the browser, sense 
checked again the back end on the server.  Assuming they're okay, the model is 
generated and returned to the browser as a KMZ file.
