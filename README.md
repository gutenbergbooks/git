#Outline of source control at Project Gutenberg

Each ebook is its own Git repository.

Repositories have their group set to the `committers` Unix group, with group write permission enabled, and with the sticky bit set so that group ownership persists after Git operations.  Thus, any Unix users in the `committers` group can write to an ebook repository.

To assist in persisting permissions, the `post-merge` and `post-checkout` hooks reset group write permissions.

##Initializing a new Git repo

Use the `/data/git/init-pg-repo` script to initialze one or more Git repos.  This script automatically creates the hooks described above, connects to GitHub to create the corresponding repository, and sets up remote branch information.  The script uses credentials for the `gutenbergbooks-bot` GitHub user (see below for details), so you don't need to have a GitHub account to run this script.  It does, however, require `chmod` permission (typically requiring `sudo`) in order to set repository permissions.

##Project Gutenberg and GitHub

Local repositories each have a corresponding GitHub repository, named after the ebook ID number, and a remote branch, named `github`, linked to that GitHub repository.  Thus, `git push github` pushes changes from the local repository to the corresponding GitHub repository.

The `post-commit` hook is set to automatically execute `git push --quiet github` after every local commit.

On GitHub, the `gutenbergbooks` organization has a webhook set up to ping https://crowd.pglaf.org/webhook-listeners/github.php (filesystem: `/data/crowd/webhook-listeners/github.php`) every time a commit is made to an ebook repository.  This webhook locates the corresponding ebook repository on the pglaf.org filesystem and executes `git pull github` to synchronize the local repository with changes that have occured at GitHub.  Since the webhook as the `www-data` Unx user, it uses `sudo` to impersonate the local `github` Unix user to allow for repository write access during pull operations.  See `/etc/sudoers.d/github` for sudo configuration.

The webhook listener keeps a log file at `/data/git/webhooks-github.log`.

On Github, the `gutenbergbooks-bot` GitHub user is how we create, push, and pull to repositories.  The user has a personal access token set up that is hard-coded both the `init-pg-repo` script, and in the remote branch definition in each ebook repository.  We use this personal access token method because automating SSH over `sudo -u` is extremely difficult.
