image=yiimp
version=v0.9
MAILADDRESS='user@example.com'
DOMAINNAME='yiimp.example.com' 

# Ubuntu release of the base image (Dockerfile.yiimp: FROM ubuntu:24.04)
UBUNTU_CODENAME=noble
UBUNTU_INDEXES=http://archive.ubuntu.com/ubuntu/dists/$(UBUNTU_CODENAME)/InRelease \
	http://archive.ubuntu.com/ubuntu/dists/$(UBUNTU_CODENAME)-updates/InRelease \
	http://security.ubuntu.com/ubuntu/dists/$(UBUNTU_CODENAME)-security/InRelease

# Hash of the Ubuntu release indexes: cache key of the OS-upgrade layer.
# Same hash -> layer from cache; Ubuntu published something -> layer runs once.
# A failed fetch stops the build instead of silently keeping a stale layer.
UBUNTU_STATE_CMD=idx=$$(for u in $(UBUNTU_INDEXES); do curl -fsSL "$$u" || exit 1; done) \
	|| { echo "cannot fetch Ubuntu release indexes" >&2; exit 1; }; \
	state=$$(printf '%s' "$$idx" | sha256sum | cut -c1-16); \
	echo "Ubuntu package state: $$state"

# --pull-always: refetch base images the registry has renewed (podman 3.4 has no --pull=newer)
build:
	git submodule init && git submodule update
	$(UBUNTU_STATE_CMD); \
	podman build --pull-always --build-arg UBUNTU_STATE=$$state --tag $(image) --target image-prod -f Dockerfile.yiimp
build-devel:
	git submodule init && git submodule update
	$(UBUNTU_STATE_CMD); \
	podman build --pull-always --build-arg UBUNTU_STATE=$$state --tag $(image) --target image-devel -f Dockerfile.yiimp

push:
	podman push $(image) ghcr.io/tpfuemp/$(image):$(version)

# runtime/yiimp2: Yii2 runtime (file cache: paused jobs, locks) survives container recreation;
# must be writable by www-data (uid 33) inside the container.
run:
	mkdir -p runtime/yiimp2 && podman rm -i $(image) && podman run -dt --name=$(image) --network=host -v ./runtime/yiimp2:/var/yiimp2/runtime -v ./config/letsencrypt:/etc/letsencrypt -v ./config:/etc/yiimp -v ./log:/var/log/apache2 -v ./log:/var/log/yiimp -v ./config/supervisord.conf:/etc/supervisor/conf.d/supervisord.conf $(image)
run-init-letsencrypt:
	mkdir -p runtime/yiimp2 && podman rm -i $(image) && podman run -dt --name=$(image) --network=host -v ./runtime/yiimp2:/var/yiimp2/runtime -e MAILADDRESS=$(MAILADDRESS) -e DOMAINNAME=$(DOMAINNAME) -v ./config/letsencrypt:/etc/letsencrypt -v ./config:/etc/yiimp -v ./log:/var/log/apache2 -v ./log:/var/log/yiimp -v ./config/supervisord.conf:/etc/supervisor/conf.d/supervisord.conf $(image) /usr/local/bin/letsencrypt-yiimp-initial-cert.sh

run-devel:
	podman rm -i $(image) && podman run -dt --name=$(image) --network=host -v ./config/letsencrypt:/etc/letsencrypt -v ./config:/etc/yiimp -v ./yiimp2:/var/yiimp2/ -v ./log:/var/log/apache2 -v ./log:/var/log/yiimp -v ./config/supervisord.conf:/etc/supervisor/conf.d/supervisord.conf $(image) /usr/bin/supervisord
