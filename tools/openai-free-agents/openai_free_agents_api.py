from flask import Flask; app = Flask(__name__); @app.route("/v1/chat/completions", methods=["POST"]) def chat(): return {"choices":[{"message":{"content":"ok"}}]}; app.run(host="0.0.0.0", port=6400)
