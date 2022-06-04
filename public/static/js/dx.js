// JS is for dynamically creating and moving the birds across the screen.
// The actual bird flapping and flight wave is CSS animation.

// Adjust these options here to customize the scene.
let options = {
	delay: 500,
	speedRange: [2, 5],
	angleRange: [-30, 30],
	sizeRange: [15, 30],
};

let bird = document.createElement('span');
bird.className = 'bird';
let particles = [];
let length = 12;
let isLeave = false;

init();

function init() {
	for (let i = 0; i < length; i++) {
		let particle = initParticle();
		particle.move();
		particles.push(particle);
	}
}

function initPos() {
	var top = $('.d1').offset().top + 50;
	var bottom = $('.d1').height() / 1.8 + top;
	return [rand(50, window.innerWidth / 2), rand(top, bottom)];
}

function initParticle() {
	let newBird = bird.cloneNode();
	const size = rand(options.sizeRange[0], options.sizeRange[1]);
	newBird.style.width = size + 'px';
	newBird.style.height = size / 5 + 'px';

	document.querySelector('.animate-bg').appendChild(newBird);

	let pos = initPos();

	return new Particle(newBird, {
		speed: rand(options.speedRange[0], options.speedRange[1]),
		angle: rand(options.angleRange[0], options.angleRange[1]),
		pos: pos,
	});
}

window.requestAnimationFrame(draw);

function draw() {
	particles.forEach((particle, i, arr) => {
		if (particle.element.style.display == 'none') {
			particle.element.style.display = 'inline-block';
			particle.pos = initPos();
		}

		if (particle.pos[0] > window.innerWidth || particle.pos[1] > window.innerHeight || particle.pos[0] < 0 - window.innerWidth || particle.pos[1] < 0 - window.innerHeight) {
			particle.element.style.display = 'none';
		} else {
			particle.move();
		}
	});

	window.requestAnimationFrame(draw);
}

function Particle(element, options) {
	this.size = 1;
	this.speed = 1;
	this.angle = 90;
	this.pos = [0, 0];
	this.element = element;

	this.constructor = function (options) {
		for (let i in options) {
			this[i] = options[i];
		}
	};

	this.move = function () {
		var radians = (this.angle * Math.PI) / 180;
		this.pos[0] += Math.cos(radians) * this.speed;
		this.pos[1] += Math.sin(radians) * this.speed;
		// console.log(this.pos)
		this.draw();
	};

	this.draw = function () {
		this.element.style.left = this.pos[0] + 'px';
		this.element.style.top = this.pos[1] + 'px';
	};

	this.constructor(options);
}

function rand(min, max) {
	return Math.random() * (max - min) + min;
}
