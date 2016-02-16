# torch-rnn
torch-rnn provides high-performance, reusable RNN and LSTM modules for torch7, and uses these modules for character-level
language modeling similar to [char-rnn](https://github.com/karpathy/char-rnn).

You can find documentation for the RNN and LSTM modules [here](modules.md); they have no dependencies other than `torch`
and `nn`, so they should be easy to integrate into existing projects.

Compared to char-rnn, torch-rnn is up to **1.9x faster** and uses up to **7x less memory**. For more details see 
the [Benchmark](#benchmarks) section below.


# TODOs
- CPU support
- OpenCL support?
- Get rid of Python / JSON / HDF5 dependencies?
- Documentation
  - Dependencies / installation
  - VanillaRNN
  - LSTM
  - LanguageModel
  - preprocess.py
  - train.lua
  - sample.lua

# Installation
## Python setup
The preprocessing script is written in Python 2.7; its dependencies are in the file `requirements.txt`.
You can install these dependencies in a virtual environment like this:

```bash
virtualenv .env                  # Create the virtual environment
source .env/bin/activate         # Activate the virtual environment
pip install -r requirements.txt  # Install Python dependencies
# Work for a while ...
deactivate                       # Exit the virtual environment
```

## Lua setup
The main modeling code is written in Lua using [torch](http://torch.ch); you can find installation instructions
[here](http://torch.ch/docs/getting-started.html#_). You'll need the following Lua packages:

- [torch/torch7](https://github.com/torch/torch7)
- [torch/nn](https://github.com/torch/nn)
- [torch/optim](https://github.com/torch/optim)
- [lua-cjson](https://luarocks.org/modules/luarocks/lua-cjson)
- [torch-hdf5](https://github.com/deepmind/torch-hdf5)

After installing torch, you can install / update these packages by running the following:

```bash
# Install most things using luarocks
luarocks install torch
luarocks install nn
luarocks install optim
luarocks install lua-cjson

# We need to install torch-hdf5 from GitHub
git clone git@github.com:deepmind/torch-hdf5.git
cd torch-hdf5
luarocks make hdf5-0-0.rockspec
```

### CUDA support
To enable GPU acceleration with CUDA, you'll need to install CUDA 6.5 or higher and the following Lua packages:
- [torch/cutorch](https://github.com/torch/cutorch)
- [torch/cunn](https://github.com/torch/cunn)

You can install / update them by running:

```bash
luarocks install cutorch
luarocks install cunn
```

# Usage
To train a model and use it to generate new text, you'll need to follow three simple steps:

## Step 1: Preprocess the data
You can use any text file for training models. Before training, you'll need to preprocess the data using the script
`scripts/preprocess.py`; this will generate an HDF5 file and JSON file containing a preprocessed version of the data.

If you have training data stored in `my_data.txt`, you can run the script like this:

```bash
python scripts/preprocess.py \
  --input_txt my_data.txt \
  --output_h5 my_data.h5 \
  --output_json my_data.json
```

This will produce files `my_data.h5` and `my_data.json` that will be passed to the training script.

There are a few more flags you can use to configure preprocessing; [read about them here](flags.md#preprocessing)

## Step 2: Train the model
After preprocessing the data, you'll need to train the model using the `train.lua` script. This will be the slowest step.
You can run the training script like this:

```bash
th train.lua --input_h5 my_data.h5 --input_json my_data.json
```

This will read the data stored in `my_data.h5` and `my_data.json`, run for a while, and save checkpoints to files with 
names like `cv/checkpoint_1000.t7`.

You can change the RNN type, hidden state size, and number of RNN layers like this:

```bash
th train.lua --input_h5 my_data.h5 --input_json my_data.json -rnn_type rnn -num_layers 3 -rnn_size 256
```

By default this will run in GPU mode using CUDA; to run in CPU-only mode, add the flag `-gpu -1`.

There are many more flags you can use to configure training; [read about them here](flags.md#training).

## Step 3: Sample from the model
After training a model, you can generate new text by sampling from it using the script `sample.lua`. You'll typically run
it like this:

```bash
th sample.lua -checkpoint cv/checkpoint_10000.t7 -length 2000
```

This will load the trained checkpoint `cv/checkpoint_10000.t7` from the previous step, sample 2000 characters from it,
and print the results to the console.

By default the sampling script will run in GPU mode using CUDA; to run in CPU-only mode add the flag `-gpu -1`.

There are more flags you can use to configure sampling; [read about them here](flags.md#sampling).

# Benchmarks

<img src='imgs/lstm_time_benchmark.png' width="400px">
<img src='imgs/lstm_memory_benchmark.png' width="400px">
