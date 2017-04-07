# Outline of source control at Project Gutenberg

Each ebook is its own Git repository.

Repositories have their group set to the `committers` Unix group, with group write permission enabled, and with the sticky bit set so that group ownership persists after Git operations.  Thus, any Unix users in the `committers` group can write to an ebook repository.

To assist in persisting permissions, the `post-merge` and `post-checkout` hooks reset group write permissions.

## Initializing a new Git repo

Use the `/data/git/init-pg-repo` script to initialize one or more Git repos.  This script must be run by someone in the `committers` Unix group.  It automatically creates the hooks described above, connects to GitHub to create the corresponding repository, and sets up remote branch information.  The script uses credentials for the `gutenbergbooks-github-bot` GitHub user (see below for details), so you don't need to have a GitHub account to run this script.  It does, however, require `chmod` and `chgrp` permission (typically requiring `sudo`) in order to set repository permissions.

## Quirks

Since the Gutenberg ebook build workflow is long-established, we can't use bare Git repositories on our ebooks.  So, to allow contributors not using GitHub to push changes back our local repositories (for example via SSH), each repository is initialized with `git config receive.denyCurrentBranch updateInstead`.

## Project Gutenberg and GitHub

Local repositories each have a corresponding GitHub repository, named after the ebook ID number, and a remote, named `github`, linked to that GitHub repository.  Thus, `git push github` pushes changes from the local repository to the corresponding GitHub repository.  Pushing and pulling is done over SSH using the local `gutenbergbooks-github-bot` Unix user's SSH credentials, so a GitHub account is not necessary.

The `post-commit` and `post-receive` hooks are set to automatically execute `git push --quiet github` after every commit.  `post-commit` handles the case where a local Unix user is committing to our local repo, and `post-receive` handles the case where a remote, non-GitHub repo is pushing changes to our local repo.

On GitHub, the `gutenbergbooks` organization has a webhook set up to ping https://crowd.pglaf.org/webhook-listeners/github.php (filesystem: `/data/crowd/webhook-listeners/github.php`) every time a commit is made on GitHub to an ebook repository.  This webhook locates the corresponding ebook repository on the pglaf.org filesystem and executes `git pull github` to pull down changes that have occured at GitHub.  Since the webhook runs as the `www-data` Unix user, it uses `sudo` to impersonate the local `gutenbergbooks-github-bot` Unix user to allow for SSH and repository write access during pull operations.  See `/etc/sudoers.d/github` for sudo configuration.

The webhook listener keeps a log file, including detailed error dumps, at `/data/git/webhooks-github.log`.

On Github, the `gutenbergbooks-github-bot` GitHub user is how we create, push, and pull to repositories.  The user has a personal access token, stored in `/data/crowd/secrets/`, for API access, and shares an SSH public key with the local `gutenbergbooks-github-bot` Unix user.  When connecting to GitHub, local Unix users must impersonate `gutenbergbooks-github-bot` using `sudo -u` so they can get access to `gutenbergbooks-github-bot`'s SSH key.

Note that in case of a security breach in which the `gutenbergbooks-github-bot` GitHub user's personal access token is compromised and must be re-generated, the corresponding file in the `secrets/` folder has to be updated with the new token.
