# staatus

A PHP app for enabling a "/staatus" slash command for your Slack team. Makes use of the [Silex](http://silex.sensiolabs.org/) web framework, which can easily be deployed to Heroku.

## Deploying

Install the [Heroku Toolbelt](https://toolbelt.heroku.com/).

```sh
$ git clone <this>
$ cd staatus
$ sed -i -e 's/<VERIFY_TOKEN>/<your app verification token>/g' web/index.php
$ sed -i -e 's/<AUTH_TOKEN>/<your app oauth access token>/g' web/index.php
$ git commit
$ heroku create
$ git push heroku master
$ heroku open
```

Need help connecting this app to Slack? [Read the manual](https://api.slack.com/slash-commands). Your app will require the following permissions:
- channels:read
- usergroups:read
- users:read