# staatus

A PHP app for enabling a `/staatus` Slack command for querying your team's status. Makes use of the [Silex](http://silex.sensiolabs.org/) web framework, which can easily be deployed to Heroku.

## Deploying

Install the [Heroku Toolbelt](https://toolbelt.heroku.com/).

```sh
$ git clone <this>
$ cd staatus
$ sed -i -e 's/<VERIFY_TOKEN>/<your app verification token>/g' settings.php
$ sed -i -e 's/<AUTH_TOKEN>/<your app oauth access token>/g' settings.php
$ git commit
$ heroku create
$ git push heroku master
$ heroku open
```

Need help connecting this app to Slack? [Read the manual](https://api.slack.com/slash-commands). Your app will require the following permissions:
- channels:read
- usergroups:read
- users:read