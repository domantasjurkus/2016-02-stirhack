DOGFI.SH Challenge
1.  Obtain an API key, login and password from DOGFI.SH
(get me @JrdnDncn)

2.  The teams can then visit http://dogfish.tech/api/apis to find the list of apis

3.  Apis are accessed through the format http://dogfish.tech/api/<endpoint>/<params>

For example, api1 is http://dogfish.tech/api/api1/LAX and api4 is is http://dogfish.tech/api/api4/?user_id=524549267&count=15

4.  If access type is “always”, no attional params are required.
5.  If access type is “auth”, pass your teams auth key as a get parameter called “auth”.
6.  If access type is “token”, you must hit http://dogfish.tech/api/login?user=<user>&password=<password>. This will return a token which you pass to the api endpoint as a GET parameter called “token”

The API endpoints have a 1 in 50 chance of failing in various ways. 

If you would like to force an API to fail, pass “broken=1” as GET. To force an API to work, pass “working=1” as GET.


TOKEN: 6affb8079da281abcfbe60bdd87bbbf6